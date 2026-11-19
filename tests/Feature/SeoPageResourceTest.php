<?php

namespace Tests\Feature;

use App\Filament\Resources\SeoPageResource;
use App\Models\SeoPage;
use Filament\Forms\Components\Field;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

/**
 * The administration form, the locked global record, the deletion guards and the field normalisation
 * of SeoPage.
 */
class SeoPageResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_form_is_split_into_exactly_eight_named_sections(): void
    {
        $sections = SeoPageResource::formSections();

        $this->assertSame([
            'SEO',
            'Social',
            'Sociální profily',
            'Lokalita a dostupnost',
            'Schema',
            'AEO',
            'Technické',
            'Poznámky',
        ], array_keys($sections));

        foreach ($sections as $name => $components) {
            $this->assertNotSame([], $components, 'Sekce '.$name.' je prázdná.');
        }
    }

    public function test_the_form_exposes_every_editable_field_of_the_record(): void
    {
        $fieldNames = [];

        foreach (SeoPageResource::formSections() as $components) {
            foreach ($components as $component) {
                if ($component instanceof Field) {
                    $fieldNames[] = $component->getName();
                }
            }
        }

        foreach ([
            'seo_title',
            'seo_description',
            'seo_keywords',
            'canonical_url',
            'og_title',
            'og_description',
            'og_type',
            'og_image',
            'og_image_external_url',
            'twitter_title',
            'twitter_description',
            'social_facebook_url',
            'social_instagram_url',
            'social_linkedin_url',
            'social_youtube_url',
            'schema_type',
            'schema_json',
            'aeo_summary',
            'aeo_search_intent',
            'aeo_entities',
            'aeo_faq',
            'business_name',
            'street_address',
            'address_locality',
            'postal_code',
            'address_country',
            'latitude',
            'longitude',
            'available_languages',
            'offers_online',
            'area_served',
            'availability',
            'key',
            'label',
            'route_name',
            'page_name',
            'path',
            'robots',
            'in_sitemap',
            'is_global',
            'notes',
        ] as $fieldName) {
            $this->assertContains($fieldName, $fieldNames, 'V formuláři chybí pole '.$fieldName.'.');
        }

        // Twitter/X reuses the Open Graph image, so the column must never be editable.
        $this->assertNotContains('twitter_image', $fieldNames);

        // The repeater and the short locality were replaced by the explicit fields of the brief.
        $this->assertNotContains('social_profiles', $fieldNames);
        $this->assertNotContains('locality', $fieldNames);
    }

    public function test_the_brief_fields_are_normalised_and_mirror_the_key_and_the_label(): void
    {
        $this->forgetRecord('front.doors');

        $page = SeoPage::query()->create([
            'key' => ' front.doors ',
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'canonical_url' => 'https://example.test/dvere/?utm_source=newsletter',
            'social_facebook_url' => 'https://www.facebook.com/example',
            // A bare domain is not a profile URL, so it is dropped instead of being published.
            'social_instagram_url' => 'instagram.com/example',
            'schema_type' => ' CollectionPage ',
            'aeo_entities' => ['dveře na míru', ' ', 'masiv'],
            'available_languages' => 'cs, en, cs',
            // Czech coordinates are written with a decimal comma.
            'latitude' => '49,1833333',
            'longitude' => '250',
            'offers_online' => true,
        ]);

        $page->refresh();

        // The brief names the identifiers `route_name` and `page_name`; both mirror the stored pair.
        $this->assertSame('front.doors', $page->route_name);
        $this->assertSame('Dveře', $page->page_name);

        $this->assertSame('https://example.test/dvere', $page->canonical_url);
        $this->assertSame('https://example.test/dvere', $page->resolvedCanonicalUrl());
        $this->assertSame('CollectionPage', $page->schema_type);
        $this->assertSame(['cs', 'en'], $page->available_languages);
        $this->assertSame(['dveře na míru', 'masiv'], $page->aeo_entities);
        $this->assertTrue($page->offers_online);

        $this->assertSame('https://www.facebook.com/example', $page->social_facebook_url);
        $this->assertNull($page->social_instagram_url);
        $this->assertSame(
            [['platform' => 'Facebook', 'url' => 'https://www.facebook.com/example']],
            $page->socialProfiles(),
        );

        $this->assertEqualsWithDelta(49.1833333, $page->latitude, 0.0000001);
        // The longitude is outside the range of the axis, so it is never published.
        $this->assertNull($page->longitude);
    }

    public function test_the_external_image_url_is_merged_into_the_single_og_image_column(): void
    {
        $data = SeoPageResource::mergeExternalImageUrl([
            'key' => 'front.doors',
            'og_image' => 'seo/dvere-og.webp',
            'og_image_external_url' => 'https://cdn.example.test/og/dvere.jpg',
        ]);

        $this->assertArrayNotHasKey('og_image_external_url', $data);
        $this->assertSame('https://cdn.example.test/og/dvere.jpg', $data['og_image']);
    }

    public function test_page_keys_offer_the_global_record_and_every_configured_page(): void
    {
        $options = SeoPage::pageKeyOptions();

        $this->assertArrayHasKey(SeoPage::GLOBAL_KEY, $options);
        $this->assertStringContainsString('globální', (string) $options[SeoPage::GLOBAL_KEY]);

        foreach (array_keys((array) config('seo.pages', [])) as $pageKey) {
            $this->assertArrayHasKey((string) $pageKey, $options);
        }
    }

    public function test_the_global_record_keeps_its_key_locked(): void
    {
        $this->forgetRecord(SeoPage::GLOBAL_KEY);

        $global = SeoPage::query()->create([
            'key' => SeoPage::GLOBAL_KEY,
            'label' => 'Globální nastavení',
            'seo_title' => 'Stolařství Jun a synové',
        ]);

        $this->assertTrue($global->fresh()->is_global);

        // Even an attempt to move the record onto another page keeps the locked global key.
        $global->update(['key' => 'front.doors']);

        $this->assertSame(SeoPage::GLOBAL_KEY, $global->fresh()->key);
        $this->assertTrue($global->fresh()->is_global);
    }

    public function test_a_second_global_record_can_not_be_created(): void
    {
        $this->forgetRecord(SeoPage::GLOBAL_KEY);

        SeoPage::query()->create([
            'key' => SeoPage::GLOBAL_KEY,
            'label' => 'Globální nastavení',
            'seo_title' => 'Stolařství Jun a synové',
        ]);

        $this->expectException(ValidationException::class);

        SeoPage::query()->create([
            'key' => 'front.index',
            'label' => 'Druhý globální záznam',
            'seo_title' => 'Druhý globální záznam',
            'is_global' => true,
        ]);
    }

    public function test_page_path_is_normalised_and_falls_back_to_the_route(): void
    {
        $this->forgetRecord('front.doors');

        $page = SeoPage::query()->create([
            'key' => 'front.doors',
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
        ]);

        // No stored path means the canonical follows the route of the record.
        $this->assertSame('/dvere', $page->resolvedPath());

        $page->update(['path' => 'https://example.test/dvere-na-miru/?utm_source=newsletter']);

        $this->assertSame('/dvere-na-miru', $page->fresh()->path);
        $this->assertSame('/dvere-na-miru', $page->fresh()->resolvedPath());

        $page->update(['path' => 'dvere/']);

        $this->assertSame('/dvere', $page->fresh()->path);
    }

    public function test_the_single_og_image_column_accepts_a_path_or_an_url_and_feeds_twitter_image(): void
    {
        Storage::fake(SeoPage::OG_IMAGE_DISK, ['url' => 'https://example.test/storage']);

        $this->forgetRecord('front.doors');

        $page = SeoPage::query()->create([
            'key' => 'front.doors',
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'og_image' => 'seo/dvere-og.webp',
            // Not fillable on purpose, so an attempt to set it has no effect.
            'twitter_image' => 'https://cdn.example.test/og/podvrzeno.jpg',
        ]);

        $this->assertSame('https://example.test/storage/seo/dvere-og.webp', $page->ogImageUrl());
        $this->assertSame('seo/dvere-og.webp', $page->fresh()->twitter_image);
        $this->assertSame($page->ogImageUrl(), $page->twitterImageUrl());

        $page->update(['og_image' => 'javascript:alert(1)']);

        $this->assertNull($page->fresh()->og_image);
        $this->assertNull($page->fresh()->twitter_image);
        $this->assertNull($page->fresh()->ogImageUrl());

        $page->update(['og_image' => 'https://cdn.example.test/og/dvere.png']);

        $this->assertSame('https://cdn.example.test/og/dvere.png', $page->fresh()->ogImageUrl());
        $this->assertSame('https://cdn.example.test/og/dvere.png', $page->fresh()->twitterImageUrl());
    }

    public function test_social_profiles_are_validated_and_normalised(): void
    {
        $this->forgetRecord('front.doors');

        $page = SeoPage::query()->create([
            'key' => 'front.doors',
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'social_profiles' => [
                ['platform' => ' Facebook ', 'url' => 'https://www.facebook.com/example'],
                ['platform' => 'Instagram', 'url' => 'instagram.com/example'],
                ['platform' => '', 'url' => 'https://www.linkedin.com/company/example'],
            ],
        ]);

        $this->assertSame(
            [['platform' => 'Facebook', 'url' => 'https://www.facebook.com/example']],
            $page->fresh()->socialProfiles(),
        );
    }

    public function test_bulk_deletion_is_refused_for_the_whole_application(): void
    {
        $this->forgetRecord('front.doors');

        SeoPage::query()->create([
            'key' => 'front.doors',
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
        ]);

        try {
            SeoPage::query()->where('key', 'front.doors')->delete();
            $this->fail('Bulk delete must be refused.');
        } catch (LogicException) {
            $this->assertDatabaseHas('seo_pages', ['key' => 'front.doors', 'deleted_at' => null]);
        }

        $this->expectException(LogicException::class);

        SeoPage::withTrashed()->where('key', 'front.doors')->forceDelete();
    }

    /**
     * The guards make the model refuse every delete, so the known state is prepared with raw SQL.
     */
    private function forgetRecord(string $pageKey): void
    {
        DB::table('seo_pages')->where('key', $pageKey)->delete();
    }
}
