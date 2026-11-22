<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The catalog page is a regular content page of the public site: it shares the header of the other
 * subpages, is listed in the site menu and publishes its own SEO record.
 */
class CatalogPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests render the Inertia root view, so Vite must not require a production build manifest.
        $hotFile = storage_path('framework/testing/vite-hot');
        File::ensureDirectoryExists(dirname($hotFile));
        File::put($hotFile, 'http://127.0.0.1:5173');

        Vite::useHotFile($hotFile);

        config(['app.url' => 'https://example.test']);
    }

    public function test_catalog_page_is_rendered_through_the_catalog_component(): void
    {
        $this->get(route('front.catalog'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                // The frontend pages live outside Inertia's default page path, so the file check is disabled here.
                ->component('Catalog', false)
            );
    }

    public function test_catalog_page_publishes_its_configured_seo_record(): void
    {
        $this->get(route('front.catalog'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('seo.key', 'front.catalog')
                ->where('seo.is_global', false)
                ->where('seo.title', 'Katalog – modely a provedení ze dřeva | Stolařství Jun a synové')
                ->where('seo.canonical', 'https://example.test/katalog')
                ->where('seo.path', '/katalog')
                ->where('seo.robots', 'index,follow')
            );
    }

    public function test_catalog_page_is_listed_in_the_sitemap(): void
    {
        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('<loc>https://example.test/katalog</loc>', false);
    }
}
