<?php

namespace Tests\Feature;

use App\Models\SeoPage;
use Database\Seeders\SeoAeoSuggestionsSeeder;
use Database\Seeders\SeoPagesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Both SEO seeders must be safe to run repeatedly: the first one creates the records of the real
 * public pages plus the global record, the second one only enriches what is still empty and works
 * even on a database where no SEO record exists yet.
 *
 * The suggestion seeder is standalone: DatabaseSeeder must never run it automatically.
 */
class SeoPagesSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_seeder_creates_the_global_record_and_one_record_per_public_page(): void
    {
        $this->forgetConfiguredRecords();

        $this->seed(SeoPagesSeeder::class);

        $global = SeoPage::query()->where('key', SeoPage::GLOBAL_KEY)->first();

        $this->assertInstanceOf(SeoPage::class, $global);
        $this->assertTrue($global->is_global);
        $this->assertSame('Zastávka u Brna', $global->locality);

        // The brief fields are seeded under their exact column names.
        $this->assertSame(SeoPage::GLOBAL_KEY, $global->route_name);
        $this->assertSame('Globální nastavení', $global->page_name);
        $this->assertSame('Stolařství Jun a synové s.r.o.', $global->business_name);
        $this->assertSame('Stará Osada 540', $global->street_address);
        $this->assertSame('Zastávka u Brna', $global->address_locality);
        $this->assertSame('664 84', $global->postal_code);
        $this->assertSame('CZ', $global->address_country);
        $this->assertSame('Carpenter', $global->schema_type);
        $this->assertSame(['cs'], $global->available_languages);
        $this->assertTrue($global->offers_online);
        $this->assertNotNull($global->aeo_summary);
        $this->assertSame('Informační – hledá stolařství v Brně a okolí a zjišťuje, co všechno dílna umí vyrobit ze dřeva.', $global->aeo_search_intent);
        $this->assertSame(['stolařství Brno', 'dveře na míru', 'zakázková výroba ze dřeva', 'Zastávka u Brna'], $global->aeo_entities);

        foreach ($this->routedPageKeys() as $pageKey) {
            $this->assertDatabaseHas('seo_pages', ['key' => $pageKey, 'is_global' => false]);
        }

        $this->assertSame(
            count($this->configuredPageKeys()),
            SeoPage::query()->whereIn('key', $this->configuredPageKeys())->count(),
        );
        $this->assertSame('noindex,follow', SeoPage::query()->where('key', 'front.gdpr')->value('robots'));

        // The seeded record uses the requested column names, not only the short config keys.
        $this->assertDatabaseHas('seo_pages', [
            'key' => 'front.doors',
            'seo_title' => 'Interiérové a vchodové dveře na míru',
            'route_name' => 'front.doors',
            'page_name' => 'Dveře',
            'schema_type' => 'CollectionPage',
        ]);

        $doors = SeoPage::query()->where('key', 'front.doors')->firstOrFail();

        $this->assertSame('front.doors', $doors->route_name);
        $this->assertSame('Dveře', $doors->page_name);
        $this->assertNotNull($doors->aeo_summary);
        // The address belongs to the global record, so a page never repeats it.
        $this->assertNull($doors->address_locality);
        $this->assertSame(
            ['interiérové dveře', 'vchodové dveře', 'bezfalcové dveře', 'masivní dřevo', 'atypické rozměry'],
            $doors->aeo_entities,
        );
    }

    public function test_seeder_skips_configuration_entries_without_a_real_route(): void
    {
        $this->forgetConfiguredRecords();

        config(['seo.pages' => [
            ...(array) config('seo.pages'),
            'front.missingPage' => [
                'label' => 'Neexistující stránka',
                'title' => 'Neexistující stránka',
            ],
        ]]);

        $this->seed(SeoPagesSeeder::class);

        $this->assertDatabaseMissing('seo_pages', ['key' => 'front.missingPage']);
        $this->assertDatabaseHas('seo_pages', ['key' => 'front.contact']);
    }

    public function test_seeder_is_idempotent_and_keeps_edits_from_the_administration(): void
    {
        $this->forgetConfiguredRecords();

        $this->seed(SeoPagesSeeder::class);

        $doors = SeoPage::query()->where('key', 'front.doors')->firstOrFail();
        $doors->update(['seo_title' => 'Vlastní titulek z administrace']);

        $this->seed(SeoPagesSeeder::class);

        $this->assertSame(
            count($this->configuredPageKeys()),
            SeoPage::query()->whereIn('key', $this->configuredPageKeys())->count(),
        );
        $this->assertSame('Vlastní titulek z administrace', $doors->fresh()->seo_title);
        $this->assertSame(1, SeoPage::withTrashed()->where('key', SeoPage::GLOBAL_KEY)->count());
    }

    public function test_the_suggestion_seeder_is_not_registered_in_the_database_seeder(): void
    {
        // The AEO suggestions are an on demand enrichment, never a part of a fresh database.
        $this->assertStringNotContainsString(
            'SeoAeoSuggestionsSeeder',
            File::get(database_path('seeders/DatabaseSeeder.php')),
        );
    }

    public function test_suggestion_seeder_creates_records_without_any_existing_page(): void
    {
        $this->forgetConfiguredRecords();

        $this->seed(SeoAeoSuggestionsSeeder::class);

        $doors = SeoPage::query()->where('key', 'front.doors')->first();

        $this->assertInstanceOf(SeoPage::class, $doors);
        // The record is created from config/seo.php, so the seeder never needs a prepared table.
        $this->assertSame('Interiérové a vchodové dveře na míru', $doors->seo_title);
        $this->assertNotEmpty($doors->faqItems());
        $this->assertSame(0, SeoPage::query()->where('key', SeoPage::GLOBAL_KEY)->count());
    }

    public function test_suggestion_seeder_merges_keywords_and_keeps_edited_copy(): void
    {
        $this->forgetConfiguredRecords();

        SeoPage::query()->create([
            'key' => 'front.doors',
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'seo_keywords' => 'vlastní klíčové slovo',
            'aeo_faq' => [['question' => 'Vlastní otázka?', 'answer' => 'Vlastní odpověď.']],
        ]);

        $this->seed(SeoAeoSuggestionsSeeder::class);

        $doors = SeoPage::query()->where('key', 'front.doors')->firstOrFail();

        $this->assertStringStartsWith('vlastní klíčové slovo', (string) $doors->seo_keywords);
        $this->assertStringContainsString('vchodové dveře na míru', (string) $doors->seo_keywords);
        $this->assertSame([['question' => 'Vlastní otázka?', 'answer' => 'Vlastní odpověď.']], $doors->aeo_faq);

        // A second run must not change anything, keyword merging included.
        $keywords = (string) $doors->seo_keywords;

        $this->seed(SeoAeoSuggestionsSeeder::class);

        $this->assertSame($keywords, (string) $doors->fresh()->seo_keywords);
        $this->assertSame([['question' => 'Vlastní otázka?', 'answer' => 'Vlastní odpověď.']], $doors->fresh()->aeo_faq);
    }

    /**
     * @return list<string>
     */
    private function configuredPageKeys(): array
    {
        return array_map('strval', array_keys((array) config('seo.pages', [])));
    }

    /**
     * @return list<string>
     */
    private function routedPageKeys(): array
    {
        return array_values(array_filter(
            $this->configuredPageKeys(),
            fn (string $pageKey): bool => $pageKey !== SeoPage::GLOBAL_KEY && Route::has($pageKey),
        ));
    }

    /**
     * The seeder assertions need a known state. The model refuses every delete, so the records are
     * removed with raw SQL inside the transaction the test runs in.
     */
    private function forgetConfiguredRecords(): void
    {
        DB::table('seo_pages')->whereIn('key', $this->configuredPageKeys())->delete();
    }
}
