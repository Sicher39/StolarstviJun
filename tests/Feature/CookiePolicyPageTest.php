<?php

namespace Tests\Feature;

use App\Enums\Compliance\TrackingCategory;
use App\Models\LegalDocument;
use App\Models\TrackingScript;
use App\Services\Legal\LegalContentRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public cookie policy at /zasady-cookies.
 *
 * The page publishes the cookie policy document of the administration, or the configured baseline while
 * the record is only a draft, and it publishes the cookies and services the site really uses exactly once:
 * at the `{{ cookie_services }}` token when the text places it, below the document otherwise.
 */
class CookiePolicyPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // The test renders the Inertia root view, so Vite must not require a production build manifest.
        $hotFile = storage_path('framework/testing/vite-hot');
        File::ensureDirectoryExists(dirname($hotFile));
        File::put($hotFile, 'http://127.0.0.1:5173');

        Vite::useHotFile($hotFile);

        DB::table('legal_documents')->delete();
        DB::table('tracking_scripts')->delete();
    }

    public function test_the_cookie_policy_has_its_own_public_route(): void
    {
        $this->assertTrue(Route::has('front.cookies'));
        $this->assertSame(url('/zasady-cookies'), route('front.cookies'));
    }

    public function test_the_page_renders_the_configured_baseline_while_no_record_exists(): void
    {
        $this->get(route('front.cookies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                // The frontend pages live outside Inertia's default page path, so the file check is disabled.
                ->component('CookiePolicy', false)
                ->where('document.title', 'Zásady používání cookies')
                ->where('privacy_policy_url', route('front.gdpr'))
                ->where('document.content', fn (mixed $content): bool => is_string($content)
                    && str_contains($content, 'Stolařství Jun a synové s.r.o.')
                    && str_contains($content, 'Nezbytné cookies')
                    && ! str_contains($content, '{{')));
    }

    public function test_the_baseline_is_a_reviewable_template_with_visible_placeholders(): void
    {
        $renderer = app(LegalContentRenderer::class);
        $baseline = LegalDocument::baseline(LegalDocument::TYPE_COOKIE_POLICY);

        $placeholders = $renderer->placeholders($baseline->content);

        // The repository can not verify the effective date or the third-party tools, so both stay a
        // placeholder the operator has to fill in - nothing is invented.
        $this->assertContains('datum účinnosti', $placeholders);
        $this->assertStringContainsString('[DOPLNIT:', $renderer->render($baseline->content));
    }

    public function test_the_seeded_cookie_policy_stays_a_draft(): void
    {
        $attributes = LegalDocument::attributesFromDefinition(LegalDocument::TYPE_COOKIE_POLICY);

        $this->assertNull($attributes['published_at']);
        $this->assertNotNull(LegalDocument::attributesFromDefinition(LegalDocument::TYPE_PRIVACY_POLICY)['published_at']);
    }

    public function test_the_overview_of_cookies_is_inserted_at_the_token(): void
    {
        $this->trackingScript('Relace prohlížení', TrackingCategory::Necessary);

        $this->publishedCookiePolicy('<p>Úvod</p>{{ cookie_services }}<p>Závěr</p>');

        $this->get(route('front.cookies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('CookiePolicy', false)
                ->where('document.content', fn (mixed $content): bool => is_string($content)
                    && str_contains($content, 'Relace prohlížení')
                    && ! str_contains($content, '{{ cookie_services }}')
                    && str_contains($content, 'Závěr')));
    }

    public function test_a_text_without_the_token_still_publishes_the_overview_once(): void
    {
        $this->trackingScript('Relace prohlížení', TrackingCategory::Necessary);

        $this->publishedCookiePolicy('<p>Text bez místa vložení.</p>');

        $this->get(route('front.cookies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('CookiePolicy', false)
                ->where('document.content', fn (mixed $content): bool => is_string($content)
                    && str_contains($content, 'Text bez místa vložení.')
                    && substr_count($content, 'Relace prohlížení') === 1));
    }

    public function test_a_disabled_service_is_not_published(): void
    {
        $this->trackingScript('Vypnutý nástroj', TrackingCategory::Analytics, enabled: false);

        $this->publishedCookiePolicy('<p>{{ cookie_services }}</p>');

        $this->get(route('front.cookies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('CookiePolicy', false)
                ->where('document.content', fn (mixed $content): bool => is_string($content)
                    && ! str_contains($content, 'Vypnutý nástroj')));
    }

    private function publishedCookiePolicy(string $content): LegalDocument
    {
        return LegalDocument::query()->create([
            'type' => LegalDocument::TYPE_COOKIE_POLICY,
            'locale' => LegalDocument::DEFAULT_LOCALE,
            'title' => 'Zásady používání cookies',
            'content' => $content,
            'version' => '1.0',
            'published_at' => now(),
        ]);
    }

    private function trackingScript(string $name, TrackingCategory $category, bool $enabled = true): TrackingScript
    {
        return TrackingScript::query()->create([
            'name' => $name,
            'category' => $category,
            'enabled' => $enabled,
            'requires_consent' => $category->isOptional(),
            'priority' => 10,
        ]);
    }
}
