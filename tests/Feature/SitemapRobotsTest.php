<?php

namespace Tests\Feature;

use App\Models\SeoPage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * /sitemap.xml and /robots.txt are derived from config('app.url'), never from the host of the
 * current request, so the URLs stay stable behind proxies and on preview environments.
 */
class SitemapRobotsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://example.test']);
    }

    public function test_sitemap_lists_every_indexable_frontend_page(): void
    {
        $response = $this->get(route('seo.sitemap'));

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->baseResponse->headers->get('Content-Type'));

        foreach (['/', '/dvere', '/konfigurator', '/zakazkova-vyroba', '/reference', '/kontakt'] as $path) {
            $response->assertSee('<loc>https://example.test'.$path.'</loc>', false);
        }
    }

    public function test_sitemap_never_contains_the_gdpr_page(): void
    {
        $this->get(route('seo.sitemap'))
            ->assertOk()
            // The page body still comes from another project, so it is neither listed nor indexed.
            ->assertDontSee('https://example.test/ochrana-osobnich-udaju', false);
    }

    public function test_sitemap_skips_a_page_marked_as_noindex(): void
    {
        $this->recordFor('front.doors', [
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'robots' => 'noindex,follow',
        ]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertDontSee('https://example.test/dvere', false);
    }

    public function test_sitemap_uses_the_path_stored_in_the_record(): void
    {
        $this->recordFor('front.doors', [
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'path' => '/dvere-na-miru',
        ]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('<loc>https://example.test/dvere-na-miru</loc>', false)
            ->assertDontSee('<loc>https://example.test/dvere</loc>', false);
    }

    public function test_sitemap_lists_the_canonical_url_pinned_in_the_record(): void
    {
        $this->recordFor('front.doors', [
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'path' => '/dvere-na-miru',
            'canonical_url' => 'https://example.test/dvere-finalni',
        ]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('<loc>https://example.test/dvere-finalni</loc>', false)
            ->assertDontSee('<loc>https://example.test/dvere-na-miru</loc>', false);
    }

    public function test_sitemap_keeps_the_own_address_when_the_canonical_points_elsewhere(): void
    {
        $this->recordFor('front.doors', [
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'canonical_url' => 'https://jiny-web.test/dvere',
        ]);

        // A canonical that names another domain must not move the page out of this sitemap.
        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertSee('<loc>https://example.test/dvere</loc>', false)
            ->assertDontSee('jiny-web.test', false);
    }

    public function test_sitemap_skips_a_page_excluded_in_its_record(): void
    {
        $this->recordFor('front.doors', [
            'label' => 'Dveře',
            'seo_title' => 'Dveře na míru',
            'robots' => 'index,follow',
            'in_sitemap' => false,
        ]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertDontSee('https://example.test/dvere', false);
    }

    public function test_sitemap_never_lists_the_global_record(): void
    {
        $this->recordFor(SeoPage::GLOBAL_KEY, [
            'label' => 'Globální nastavení',
            'seo_title' => 'Stolařství Jun a synové',
        ]);

        // The global record has no page, so no entry may be produced for it.
        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertDontSee('https://example.test/global', false);
    }

    public function test_robots_txt_points_to_the_sitemap(): void
    {
        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertSee('User-agent: *', false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Sitemap: https://example.test/sitemap.xml', false);
    }

    public function test_robots_txt_is_not_shadowed_by_a_static_file(): void
    {
        // A file in public/ would be served before Laravel is reached, which silently dropped the
        // sitemap line in production, so robots.txt is answered by the route only.
        $this->assertFalse(File::exists(public_path('robots.txt')));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordFor(string $pageKey, array $attributes): SeoPage
    {
        return SeoPage::query()->updateOrCreate(['key' => $pageKey], $attributes);
    }
}
