<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\DataProcessingPurpose;
use App\Models\LegalDocument;
use App\Models\PrivacySetting;
use App\Services\Legal\LegalContentRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The public privacy page must keep its route and be driven by the legal document record only.
 *
 * The text is checked for two properties that matter in this project: it is written for this
 * woodworking workshop (nothing of the unrelated source project survived) and every legally
 * material fact that the repository can not verify stays a visible, editable placeholder.
 */
class LegalDocumentPageTest extends TestCase
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
    }

    public function test_the_privacy_page_keeps_its_route_and_name(): void
    {
        $this->assertTrue(Route::has('front.gdpr'));
        $this->assertSame(url('/ochrana-osobnich-udaju'), route('front.gdpr'));
    }

    public function test_the_page_renders_the_published_document(): void
    {
        $this->forgetDocuments();

        $this->publishedDocument([
            'title' => 'Zásady ochrany osobních údajů',
            'content' => '<p>Zveřejněný text.</p>',
            'version' => '2.1',
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                // The frontend pages live outside Inertia's default page path, so the file check is disabled here.
                ->component('Gdpr', false)
                ->where('document.title', 'Zásady ochrany osobních údajů')
                ->where('document.content', '<p>Zveřejněný text.</p>')
                ->where('document.version', '2.1')
                ->where('document.effective_from', null));
    }

    public function test_an_unpublished_draft_is_never_published_and_the_baseline_text_is_rendered(): void
    {
        $this->forgetDocuments();

        LegalDocument::query()->create([
            'type' => LegalDocument::TYPE_PRIVACY_POLICY,
            'title' => 'Koncept',
            'content' => '<p>Text konceptu.</p>',
            'version' => '9.9',
            'published_at' => null,
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gdpr', false)
                ->where('document.content', fn (mixed $content): bool => is_string($content)
                    && str_contains($content, 'Správce osobních údajů')
                    && ! str_contains($content, 'Text konceptu')));
    }

    public function test_the_contact_variables_are_substituted_and_escaped(): void
    {
        $this->forgetDocuments();

        Contact::current()->update([
            'company_name' => 'Dílna <Test> s.r.o.',
            'email' => 'test@example.test',
        ]);

        $this->publishedDocument([
            'content' => '<p>{{ company_name }} – {{ email }} – {{ unknown_variable }}</p>',
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gdpr', false)
                ->where('document.content', '<p>Dílna &lt;Test&gt; s.r.o. – test@example.test – {{ unknown_variable }}</p>'));
    }

    public function test_an_unset_retention_period_is_published_as_a_visible_placeholder(): void
    {
        $this->forgetDocuments();
        PrivacySetting::query()->delete();

        $this->publishedDocument([
            'content' => '<p>Poptávky uchováváme {{ inquiry_retention_period }}.</p>',
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gdpr', false)
                ->where('document.content', '<p>Poptávky uchováváme [DOPLNIT: doba uchovávání poptávek].</p>'));
    }

    public function test_the_configured_retention_period_appears_in_the_text_in_czech(): void
    {
        $this->forgetDocuments();
        PrivacySetting::query()->delete();
        PrivacySetting::query()->create(['inquiry_retention_months' => 24]);

        $this->publishedDocument([
            'content' => '<p>{{ inquiry_retention_period }}</p>',
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gdpr', false)
                ->where('document.content', '<p>24 měsíců</p>'));
    }

    public function test_the_configured_text_describes_this_workshop_only(): void
    {
        $this->forgetDocuments();
        PrivacySetting::query()->delete();

        $renderer = app(LegalContentRenderer::class);
        $baseline = LegalDocument::baseline(LegalDocument::TYPE_PRIVACY_POLICY);
        $rendered = $renderer->render($baseline->content);

        // Only facts that the repository really contains are published.
        $this->assertStringContainsString('Stolařství Jun a synové s.r.o.', $rendered);
        $this->assertStringContainsString('Stará Osada 540', $rendered);
        $this->assertStringContainsString('Zastávka u Brna', $rendered);
        $this->assertStringContainsString('28288505', $rendered);
        $this->assertStringContainsString('info@drevovyroba-jun.cz', $rendered);

        // Nothing of the unrelated source project may survive the rewrite.
        $this->assertStringNotContainsString('MIRA', $rendered);
        $this->assertStringNotContainsString('mirabrno.cz', $rendered);
        $this->assertStringNotContainsString('Cejl', $rendered);
        $this->assertStringNotContainsString('27745015', $rendered);

        // Legally material facts that can not be verified stay editable placeholders.
        $placeholders = $renderer->placeholders($baseline->content);
        $this->assertNotSame([], $placeholders);
        $this->assertContains('datum účinnosti', $placeholders);
        $this->assertStringContainsString('[DOPLNIT:', $rendered);
        $this->assertStringContainsString('[DOPLNIT: doba uchovávání poptávek]', $rendered);
    }

    public function test_the_processing_purposes_are_inserted_at_the_token(): void
    {
        $this->forgetDocuments();
        $this->inquiryPurpose();

        $this->publishedDocument([
            'content' => '<p>Účely zpracování</p>{{ processing_purposes }}<p>Konec textu.</p>',
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gdpr', false)
                ->where('document.content', fn (mixed $content): bool => is_string($content)
                    && str_contains($content, 'Vyřízení poptávky a příprava nabídky')
                    && str_contains($content, 'Konec textu.')
                    && ! str_contains($content, '{{ processing_purposes }}')));
    }

    public function test_a_text_without_the_token_still_publishes_the_purposes_once(): void
    {
        $this->forgetDocuments();
        $this->inquiryPurpose();

        $this->publishedDocument([
            'content' => '<p>Text bez místa vložení.</p>',
        ]);

        $this->get(route('front.gdpr'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Gdpr', false)
                ->where('document.content', fn (mixed $content): bool => is_string($content)
                    && str_contains($content, 'Text bez místa vložení.')
                    && substr_count($content, 'Vyřízení poptávky a příprava nabídky') === 1));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedDocument(array $overrides = []): LegalDocument
    {
        return LegalDocument::query()->create([
            'type' => LegalDocument::TYPE_PRIVACY_POLICY,
            'locale' => LegalDocument::DEFAULT_LOCALE,
            'title' => 'Zásady ochrany osobních údajů',
            'content' => '<p>Text.</p>',
            'version' => '1.0',
            'published_at' => now(),
            ...$overrides,
        ]);
    }

    private function forgetDocuments(): void
    {
        DB::table('legal_documents')->delete();

        // The page also publishes the active processing purposes at their token, or below the document
        // when the text places no token, so the assertions about the document text need a known and
        // empty list of purposes.
        DB::table('data_processing_purposes')->delete();
    }

    private function inquiryPurpose(): DataProcessingPurpose
    {
        return DataProcessingPurpose::query()->create([
            'name' => 'Vyřízení poptávky a příprava nabídky',
            'context' => DataProcessingPurpose::CONTEXT_INQUIRY,
            'description' => 'Zpracování poptávky z konfigurátoru.',
            'legal_basis' => 'contract',
            'retention_period' => 'Po dobu vyřízení poptávky.',
            'is_active' => true,
            'priority' => 10,
        ]);
    }
}
