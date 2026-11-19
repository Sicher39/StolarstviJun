<?php

namespace Tests\Feature;

use App\Filament\Resources\LegalDocumentResource;
use App\Models\LegalDocument;
use App\Models\User;
use BezhanSalleh\FilamentShield\Support\Utils;
use Filament\Forms\Components\Field;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The administration form, the publishing rules, the revision history and the Shield based
 * authorization of the legal documents.
 */
class LegalDocumentResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_form_is_split_into_named_sections(): void
    {
        $sections = LegalDocumentResource::formSections();

        $this->assertSame(['Dokument', 'Obsah', 'Zveřejnění', 'Poznámky'], array_keys($sections));

        foreach ($sections as $name => $components) {
            $this->assertNotSame([], $components, 'Sekce '.$name.' je prázdná.');
        }
    }

    public function test_the_form_exposes_every_editable_field_of_the_document(): void
    {
        $fieldNames = [];

        foreach (LegalDocumentResource::formSections() as $components) {
            foreach ($components as $component) {
                if ($component instanceof Field) {
                    $fieldNames[] = $component->getName();
                }
            }
        }

        foreach ([
            'type',
            'locale',
            'title',
            'perex',
            'content',
            'version',
            'effective_from',
            'published_at',
            'notes',
        ] as $fieldName) {
            $this->assertContains($fieldName, $fieldNames, 'Ve formuláři chybí pole '.$fieldName.'.');
        }

        // The helper text has to explain what the editor may write into the text.
        $this->assertStringContainsString('{{ company_name }}', LegalDocumentResource::contentHelpText());
        $this->assertStringContainsString('[DOPLNIT:', LegalDocumentResource::contentHelpText());
    }

    public function test_document_types_come_from_the_configuration(): void
    {
        $options = LegalDocument::typeOptions();

        $this->assertArrayHasKey(LegalDocument::TYPE_PRIVACY_POLICY, $options);
        $this->assertSame('Zásady ochrany osobních údajů', $options[LegalDocument::TYPE_PRIVACY_POLICY]);

        foreach (array_keys((array) config('legal.documents', [])) as $type) {
            $this->assertArrayHasKey((string) $type, $options);
        }

        // The site is Czech only, so no unpublished locale is offered.
        $this->assertSame([LegalDocument::DEFAULT_LOCALE], array_keys(LegalDocument::localeOptions()));
    }

    public function test_the_baseline_comes_from_the_configuration_and_is_not_persisted(): void
    {
        $baseline = LegalDocument::baseline(LegalDocument::TYPE_PRIVACY_POLICY);

        $this->assertTrue($baseline->isBaseline());
        $this->assertFalse($baseline->isPublished());
        $this->assertSame('Zásady ochrany osobních údajů', $baseline->title);
        $this->assertSame(LegalDocument::DEFAULT_LOCALE, $baseline->locale);
        $this->assertNotSame('', $baseline->content);
    }

    public function test_an_unknown_document_type_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        LegalDocument::baseline('unrelated-legal-page');
    }

    public function test_published_for_ignores_a_draft_and_body_for_falls_back_to_the_baseline(): void
    {
        $this->forgetDocuments();

        LegalDocument::query()->create([
            'type' => LegalDocument::TYPE_PRIVACY_POLICY,
            'title' => 'Koncept',
            'content' => '<p>Koncept.</p>',
            'published_at' => null,
        ]);

        $this->assertNull(LegalDocument::publishedFor(LegalDocument::TYPE_PRIVACY_POLICY));
        $this->assertTrue(LegalDocument::bodyFor(LegalDocument::TYPE_PRIVACY_POLICY)->isBaseline());

        $published = LegalDocument::query()->firstOrFail();
        $published->update(['published_at' => now()]);

        $this->assertSame($published->id, LegalDocument::publishedFor(LegalDocument::TYPE_PRIVACY_POLICY)?->id);
        $this->assertFalse(LegalDocument::bodyFor(LegalDocument::TYPE_PRIVACY_POLICY)->isBaseline());
    }

    public function test_a_second_document_of_the_same_type_and_locale_is_refused(): void
    {
        $this->forgetDocuments();

        $this->createDocument();

        $this->expectException(ValidationException::class);

        $this->createDocument(['title' => 'Druhý dokument']);
    }

    public function test_a_published_document_records_the_history_only_when_the_content_changes(): void
    {
        $this->forgetDocuments();

        $document = $this->createDocument(['content' => '<p>První verze.</p>', 'published_at' => now()]);

        $this->assertSame(1, $document->revisions()->count());

        // Saving the very same values must not duplicate the history.
        $document->update(['content' => '<p>První verze.</p>']);

        $this->assertSame(1, $document->revisions()->count());

        $document->update(['content' => '<p>Druhá verze.</p>', 'version' => '1.1']);

        $this->assertSame(2, $document->fresh()->revisions()->count());
        $this->assertSame('1.1', $document->fresh()->revisions()->latest('id')->firstOrFail()->version);
        $this->assertSame('<p>Druhá verze.</p>', $document->fresh()->revisions()->latest('id')->firstOrFail()->content);
    }

    public function test_a_draft_is_never_snapshotted(): void
    {
        $this->forgetDocuments();

        $document = $this->createDocument(['published_at' => null]);

        $this->assertSame(0, $document->revisions()->count());

        $document->update(['content' => '<p>Upravený koncept.</p>']);

        $this->assertSame(0, $document->revisions()->count());
    }

    public function test_the_policy_requires_a_shield_permission_or_the_super_admin_role(): void
    {
        $this->forgetDocuments();

        $document = $this->createDocument();

        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', LegalDocument::class));
        $this->assertFalse(Gate::forUser($user)->allows('update', $document));
        $this->assertFalse(Gate::forUser($user)->allows('delete', $document));

        $user->givePermissionTo(Permission::findOrCreate('ViewAny:LegalDocument', 'web'));
        $user->givePermissionTo(Permission::findOrCreate('Update:LegalDocument', 'web'));

        $this->assertTrue(Gate::forUser($user->fresh())->allows('viewAny', LegalDocument::class));
        $this->assertTrue(Gate::forUser($user->fresh())->allows('update', $document));
        // Approved documents can never be deleted, not even by a user with the update permission.
        $this->assertFalse(Gate::forUser($user->fresh())->allows('delete', $document));
    }

    public function test_the_super_admin_role_reaches_the_resource_without_generated_permissions(): void
    {
        $this->forgetDocuments();

        $role = Role::findOrCreate(Utils::getSuperAdminName(), 'web');

        $admin = User::factory()->create();
        $admin->assignRole($role);

        $this->assertTrue(Gate::forUser($admin->fresh())->allows('viewAny', LegalDocument::class));
        $this->assertTrue(Gate::forUser($admin->fresh())->allows('create', LegalDocument::class));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createDocument(array $overrides = []): LegalDocument
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
    }
}
