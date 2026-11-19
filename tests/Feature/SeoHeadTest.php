<?php

namespace Tests\Feature;

use App\Models\SeoPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The document head has to be part of the first response, because crawlers and answer engines do
 * not run the Vue application. These tests read the rendered HTML, not the Inertia payload only.
 */
class SeoHeadTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests render the Inertia root view, so Vite must not require a production build.
        $hotFile = storage_path('framework/testing/vite-hot');
        File::ensureDirectoryExists(dirname($hotFile));
        File::put($hotFile, 'http://127.0.0.1:5173');

        Vite::useHotFile($hotFile);

        config(['app.url' => 'https://example.test']);
    }

    public function test_first_response_contains_the_head_tags_rendered_by_blade(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Interiérové dveře na míru',
            'seo_description' => 'Dveře z masivu i dýhy.',
            'seo_keywords' => 'dveře na míru, vchodové dveře',
            'robots' => 'index,follow',
        ]);

        $response = $this->get(route('front.doors'));

        $response->assertOk();
        $response->assertSee('<title inertia>Interiérové dveře na míru | Stolařství Jun a synové</title>', false);
        $response->assertSee('<meta inertia head-key="description" name="description" content="Dveře z masivu i dýhy.">', false);
        $response->assertSee('<meta inertia head-key="keywords" name="keywords" content="dveře na míru, vchodové dveře">', false);
        $response->assertSee('<meta inertia head-key="robots" name="robots" content="index,follow">', false);
        $response->assertSee('<link inertia head-key="canonical" rel="canonical" href="https://example.test/dvere">', false);
        $response->assertSee('<meta inertia head-key="og:url" property="og:url" content="https://example.test/dvere">', false);
        $response->assertSee('<meta inertia head-key="og:site_name" property="og:site_name" content="Stolařství Jun a synové">', false);
        $response->assertSee('<meta inertia head-key="twitter:title" name="twitter:title" content="Interiérové dveře na míru">', false);
    }

    public function test_the_shared_payload_describes_the_current_page(): void
    {
        $this->pageFor('front.contact', [
            'seo_title' => 'Kontakt – stolařství Zastávka u Brna',
            'seo_description' => 'Ozvete se nám.',
            'robots' => 'index,follow',
            'path' => null,
        ]);

        $this->get(route('front.contact'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                // The frontend pages live outside Inertia's default page path, so the file check is disabled.
                ->component('Contact', false)
                ->where('seo.key', 'front.contact')
                ->where('seo.is_global', false)
                ->where('seo.title', 'Kontakt – stolařství Zastávka u Brna | Stolařství Jun a synové')
                ->where('seo.canonical', 'https://example.test/kontakt')
                ->where('seo.path', '/kontakt')
                ->where('seo.og_url', 'https://example.test/kontakt')
                ->where('seo.robots', 'index,follow')
                ->where('seo.og_locale', 'cs_CZ')
                ->where('seo.twitter_card', 'summary_large_image')
            );
    }

    public function test_the_shared_payload_contains_every_field_of_the_record(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'seo_description' => 'Popis stránky.',
            'seo_keywords' => 'dveře, masiv',
            'robots' => 'index,follow',
            'path' => '/dvere',
            'og_title' => 'Dveře',
            'og_description' => 'Popis pro sdílení.',
            'og_type' => 'website',
            'og_image' => 'https://cdn.example.test/og/dvere.jpg',
            'locality' => 'Zastávka u Brna',
            'area_served' => 'Brno a okolí',
            'availability' => 'Po–Pá 7:00–16:00',
            'aeo_faq' => [['question' => 'Vyrábíte atypické rozměry?', 'answer' => 'Ano, na míru.']],
            'social_profiles' => [['platform' => 'Facebook', 'url' => 'https://www.facebook.com/example']],
            'schema_json' => null,
        ]);

        $this->get(route('front.doors'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Doors', false)
                ->where('seo.key', 'front.doors')
                ->where('seo.path', '/dvere')
                ->where('seo.title', 'Dveře na míru | Stolařství Jun a synové')
                ->where('seo.description', 'Popis stránky.')
                ->where('seo.keywords', 'dveře, masiv')
                ->where('seo.canonical', 'https://example.test/dvere')
                ->where('seo.robots', 'index,follow')
                ->where('seo.og_title', 'Dveře')
                ->where('seo.og_description', 'Popis pro sdílení.')
                ->where('seo.og_type', 'website')
                ->where('seo.og_url', 'https://example.test/dvere')
                ->where('seo.locality', 'Zastávka u Brna')
                ->where('seo.area_served', 'Brno a okolí')
                ->where('seo.availability', 'Po–Pá 7:00–16:00')
                ->where('seo.faqs.0.question', 'Vyrábíte atypické rozměry?')
                ->where('seo.social_profiles.0.platform', 'Facebook')
                ->where('seo.social_profiles.0.url', 'https://www.facebook.com/example')
            );
    }

    public function test_the_payload_publishes_every_field_of_the_brief_under_its_own_name(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'schema_json' => null,
            'canonical_url' => 'https://example.test/dvere-na-miru',
            'twitter_title' => 'Dveře na míru na Twitteru',
            'twitter_description' => 'Popis pro Twitter.',
            'social_facebook_url' => 'https://www.facebook.com/example',
            'social_instagram_url' => 'https://www.instagram.com/example',
            'social_linkedin_url' => 'https://www.linkedin.com/company/example',
            'social_youtube_url' => 'https://www.youtube.com/@example',
            'schema_type' => 'CollectionPage',
            'aeo_summary' => 'Shrnutí stránky pro AI odpovědi.',
            'aeo_search_intent' => 'Transakční – výběr dveří na míru.',
            'aeo_entities' => ['dveře na míru', 'masiv'],
            'business_name' => 'Stolařství Jun a synové s.r.o.',
            'street_address' => 'Stará Osada 540',
            'address_locality' => 'Zastávka u Brna',
            'postal_code' => '664 84',
            'address_country' => 'CZ',
            'available_languages' => ['cs', 'en'],
            'latitude' => 49.1833333,
            'longitude' => 16.35,
            'offers_online' => true,
        ]);

        $this->get(route('front.doors'))
            ->assertOk()
            // The canonical is pinned by the record, and the Twitter/X tags have their own text.
            ->assertSee('<link inertia head-key="canonical" rel="canonical" href="https://example.test/dvere-na-miru">', false)
            ->assertSee('<meta inertia head-key="twitter:title" name="twitter:title" content="Dveře na míru na Twitteru">', false)
            ->assertSee('<meta inertia head-key="twitter:description" name="twitter:description" content="Popis pro Twitter.">', false)
            // The address, the coordinates and the online ordering flag reach the structured data.
            ->assertSee('"streetAddress":"Stará Osada 540"', false)
            ->assertSee('"postalCode":"664 84"', false)
            ->assertSee('"@type":"GeoCoordinates"', false)
            ->assertSee('"@type":"OrderAction"', false)
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Doors', false)
                ->where('seo.route_name', 'front.doors')
                ->where('seo.page_name', 'Dveře')
                ->where('seo.canonical_url', 'https://example.test/dvere-na-miru')
                ->where('seo.twitter_title', 'Dveře na míru na Twitteru')
                ->where('seo.twitter_description', 'Popis pro Twitter.')
                ->where('seo.social_facebook_url', 'https://www.facebook.com/example')
                ->where('seo.social_instagram_url', 'https://www.instagram.com/example')
                ->where('seo.social_linkedin_url', 'https://www.linkedin.com/company/example')
                ->where('seo.social_youtube_url', 'https://www.youtube.com/@example')
                ->where('seo.schema_type', 'CollectionPage')
                ->where('seo.aeo_summary', 'Shrnutí stránky pro AI odpovědi.')
                ->where('seo.aeo_search_intent', 'Transakční – výběr dveří na míru.')
                ->where('seo.aeo_entities.0', 'dveře na míru')
                ->where('seo.business_name', 'Stolařství Jun a synové s.r.o.')
                ->where('seo.street_address', 'Stará Osada 540')
                ->where('seo.address_locality', 'Zastávka u Brna')
                ->where('seo.postal_code', '664 84')
                ->where('seo.address_country', 'CZ')
                ->where('seo.available_languages.0', 'cs')
                ->where('seo.latitude', 49.1833333)
                ->where('seo.longitude', 16.35)
                ->where('seo.offers_online', true)
            );
    }

    public function test_the_title_is_not_suffixed_twice(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru | Stolařství Jun a synové',
            'robots' => 'index,follow',
        ]);

        $this->get(route('front.doors'))
            ->assertOk()
            ->assertSee('<title inertia>Dveře na míru | Stolařství Jun a synové</title>', false);
    }

    public function test_head_falls_back_to_the_configuration_when_the_row_is_missing(): void
    {
        // Raw SQL on purpose: the model refuses every delete of a SEO record.
        DB::table('seo_pages')->where('key', 'front.doors')->delete();

        // Route names contain dots, so the configuration is read with array access only.
        $configuration = config('seo.pages')['front.doors'];

        $this->get(route('front.doors'))
            ->assertOk()
            ->assertSee(e($configuration['title']).' | Stolařství Jun a synové', false)
            ->assertSee(e($configuration['description']), false);
    }

    public function test_canonical_falls_back_to_the_route_path_when_the_path_field_is_empty(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'path' => null,
        ]);

        $this->get(route('front.doors'))
            ->assertOk()
            ->assertSee('<link inertia head-key="canonical" rel="canonical" href="https://example.test/dvere">', false);
    }

    public function test_canonical_uses_the_stored_page_path(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            // A pasted absolute URL is reduced to its path, so the canonical never duplicates the host.
            'path' => 'https://example.test/dvere-na-miru',
        ]);

        $this->get(route('front.doors'))
            ->assertOk()
            ->assertSee('<link inertia head-key="canonical" rel="canonical" href="https://example.test/dvere-na-miru">', false)
            ->assertSee('<meta inertia head-key="og:url" property="og:url" content="https://example.test/dvere-na-miru">', false);
    }

    public function test_gdpr_page_is_noindex_and_does_not_carry_foreign_metadata(): void
    {
        $this->pageFor('front.gdpr', [
            'seo_title' => 'Ochrana osobních údajů',
            'seo_description' => null,
            'robots' => 'noindex,follow',
            'schema_json' => null,
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertSee('<meta inertia head-key="robots" name="robots" content="noindex,follow">', false)
            ->assertSee('<link inertia head-key="canonical" rel="canonical" href="https://example.test/ochrana-osobnich-udaju">', false)
            ->assertDontSee('mirabrno.cz')
            ->assertDontSee('MIRA elektromontazni');
    }

    public function test_structured_data_is_rendered_as_escaped_json(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'schema_json' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => '</script><script>alert(1)</script>',
            ],
        ]);

        $response = $this->get(route('front.doors'));

        $response->assertOk();
        $response->assertSee('<script inertia head-key="structured-data" type="application/ld+json">', false);
        $response->assertSee('\u003C', false);
        $response->assertDontSee('</script><script>alert(1)</script>', false);
    }

    public function test_company_structured_data_gets_the_site_url(): void
    {
        $this->pageFor('front.index', [
            'seo_title' => 'Dveře a zakázková výroba ze dřeva',
            'robots' => 'index,follow',
            'schema_json' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Stolařství Jun a synové s.r.o.',
            ],
        ]);

        $this->get(route('front.index'))
            ->assertOk()
            ->assertSee('"url":"https://example.test/"', false);
    }

    public function test_faqs_are_published_as_faq_page_structured_data(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'schema_json' => null,
            'aeo_faq' => [
                ['question' => 'Vyrábíte atypické rozměry?', 'answer' => 'Ano, vše na míru.'],
            ],
        ]);

        $response = $this->get(route('front.doors'));

        $response->assertOk();
        $response->assertSee('"@type":"FAQPage"', false);
        $response->assertSee('"name":"Vyrábíte atypické rozměry?"', false);
    }

    public function test_the_global_record_supplies_the_site_wide_defaults(): void
    {
        SeoPage::query()->updateOrCreate(
            ['key' => SeoPage::GLOBAL_KEY],
            [
                'label' => 'Globální nastavení',
                'seo_title' => 'Stolařství Jun a synové',
                'locality' => 'Zastávka u Brna',
                'area_served' => 'Brno a okolí',
                'og_image' => 'https://cdn.example.test/og/default.jpg',
                'social_profiles' => [['platform' => 'Instagram', 'url' => 'https://www.instagram.com/example']],
                'schema_json' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => 'Stolařství Jun a synové s.r.o.',
                ],
            ],
        );

        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'schema_json' => null,
            'locality' => null,
            'area_served' => null,
            'social_profiles' => null,
            'og_image' => null,
        ]);

        $response = $this->get(route('front.doors'));

        $response->assertOk();
        $response->assertSee('<meta inertia head-key="og:image" property="og:image" content="https://cdn.example.test/og/default.jpg">', false);
        // The global organization node is added to the page and carries the profiles as sameAs.
        $response->assertSee('"@type":"Organization"', false);
        $response->assertSee('"sameAs":["https://www.instagram.com/example"]', false);

        $this->get(route('front.doors'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('seo.locality', 'Zastávka u Brna')
                ->where('seo.area_served', 'Brno a okolí')
            );
    }

    public function test_a_page_own_structured_data_wins_over_the_global_node(): void
    {
        SeoPage::query()->updateOrCreate(
            ['key' => SeoPage::GLOBAL_KEY],
            [
                'label' => 'Globální nastavení',
                'seo_title' => 'Stolařství Jun a synové',
                'schema_json' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => 'Globální organizace',
                ],
            ],
        );

        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'schema_json' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Organizace ze stránky',
            ],
        ]);

        $response = $this->get(route('front.doors'));

        $response->assertOk();
        $response->assertSee('Organizace ze stránky', false);
        $response->assertDontSee('Globální organizace', false);
    }

    public function test_open_graph_image_is_uploaded_to_the_public_disk_and_reused_for_twitter(): void
    {
        Storage::fake(SeoPage::OG_IMAGE_DISK, ['url' => 'https://example.test/storage']);

        $page = $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'og_image' => 'seo/dvere-og.webp',
        ]);

        // The twitter_image column is filled from og_image, never from the form.
        $this->assertSame('seo/dvere-og.webp', $page->fresh()->twitter_image);

        $response = $this->get(route('front.doors'));

        $response->assertOk();
        $response->assertSee('<meta inertia head-key="og:image" property="og:image" content="https://example.test/storage/seo/dvere-og.webp">', false);
        $response->assertSee('<meta inertia head-key="twitter:image" name="twitter:image" content="https://example.test/storage/seo/dvere-og.webp">', false);
    }

    public function test_external_open_graph_image_url_is_used_as_it_is(): void
    {
        $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'og_image' => 'https://cdn.example.test/og/dvere.jpg',
        ]);

        $response = $this->get(route('front.doors'));

        $response->assertOk();
        $response->assertSee('<meta inertia head-key="og:image" property="og:image" content="https://cdn.example.test/og/dvere.jpg">', false);
        $response->assertSee('<meta inertia head-key="twitter:image" name="twitter:image" content="https://cdn.example.test/og/dvere.jpg">', false);
    }

    public function test_seo_records_can_not_be_deleted_permanently(): void
    {
        $seoPage = $this->pageFor('front.doors', [
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
        ]);

        $this->assertFalse($seoPage->forceDelete());
        $this->assertDatabaseHas('seo_pages', ['id' => $seoPage->id, 'deleted_at' => null]);

        // A soft delete stays available, so a retired page can be rebuilt later.
        $this->assertTrue($seoPage->delete());
        $this->assertSoftDeleted('seo_pages', ['id' => $seoPage->id]);
    }

    public function test_the_global_record_can_not_be_deleted_at_all(): void
    {
        $global = SeoPage::query()->updateOrCreate(
            ['key' => SeoPage::GLOBAL_KEY],
            [
                'label' => 'Globální nastavení',
                'seo_title' => 'Stolařství Jun a synové',
            ],
        );

        $this->assertFalse($global->delete());
        $this->assertFalse($global->forceDelete());
        $this->assertDatabaseHas('seo_pages', ['id' => $global->id, 'deleted_at' => null]);
    }

    /**
     * Rows may already exist in a seeded database, so every test writes the values it asserts.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function pageFor(string $pageKey, array $attributes): SeoPage
    {
        $pages = (array) config('seo.pages', []);
        $label = (string) ($pages[$pageKey]['label'] ?? $pageKey);

        return SeoPage::query()->updateOrCreate(
            ['key' => $pageKey],
            [...$attributes, 'label' => $label],
        );
    }
}
