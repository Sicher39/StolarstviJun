<?php

namespace Tests\Feature;

use App\Enums\Compliance\ConsentType;
use App\Models\ConsentRecord;
use App\Models\DataProcessingPurpose;
use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\Inquiry;
use App\Models\LegalDocument;
use App\Models\Surcharge;
use App\Services\Compliance\ConsentRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The GDPR acknowledgement of the inquiry form.
 *
 * A quote carries personal data, so it may only be accepted together with a real acknowledgement of the
 * privacy policy, and the acknowledgement has to stay provable: the record keeps its own timestamp, the
 * version of the policy that applied, the subject details and a snapshot of the submission.
 *
 * The consent is deliberately not a marketing opt-in - the form never asks for one.
 */
class InquiryGdprConsentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('consent_records')->delete();
        DB::table('data_processing_purposes')->delete();
        DB::table('legal_documents')->delete();

        LegalDocument::query()->create([
            'type' => LegalDocument::TYPE_PRIVACY_POLICY,
            'locale' => LegalDocument::DEFAULT_LOCALE,
            'title' => 'Zásady ochrany osobních údajů',
            'content' => '<p>Zkušební text.</p>',
            'version' => '2.0',
            'published_at' => now(),
        ]);

        DataProcessingPurpose::query()->create([
            'name' => 'Vyřízení poptávky a příprava nabídky',
            'context' => DataProcessingPurpose::CONTEXT_INQUIRY,
            'legal_basis' => 'contract',
            'is_active' => true,
            'priority' => 10,
        ]);
    }

    public function test_an_inquiry_without_the_acknowledgement_is_refused_and_nothing_is_stored(): void
    {
        [$doorModel, $variant] = $this->catalogSelection();

        $this->from(route('front.configurator'))
            ->post(route('front.configurator.inquiry.store'), [
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
                'surcharge_ids' => [],
                'customer_name' => 'Jan Novák',
                'customer_email' => 'jan@example.com',
            ])
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasErrors('gdpr_consent');

        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame(0, ConsentRecord::query()->count());
    }

    public function test_the_acknowledgement_is_stored_with_the_version_snapshot_and_subject(): void
    {
        [$doorModel, $variant] = $this->catalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'surcharge_ids' => [],
            'customer_name' => 'Jan Novák',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '+420 777 000 111',
            'gdpr_consent' => '1',
        ])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();
        $record = ConsentRecord::query()->firstOrFail();

        $this->assertSame(ConsentType::Gdpr, $record->type);
        $this->assertSame(ConsentRecorder::SOURCE_CONFIGURATOR_INQUIRY, $record->source);
        $this->assertSame('2.0', $record->version);
        $this->assertSame('Jan Novák', $record->subject_name);
        $this->assertSame('jan@example.com', $record->subject_email);
        $this->assertSame('+420 777 000 111', $record->subject_phone);
        $this->assertTrue($record->isAccepted());
        $this->assertNotNull($record->accepted_at);

        // The acknowledgement names the wording and the purpose it belongs to.
        $this->assertSame(ConsentRecorder::ACKNOWLEDGEMENT_TEXT, $record->preferences['acknowledgement']);
        $this->assertSame('2.0', $record->preferences['privacy_policy_version']);
        $this->assertSame($inquiry->id, $record->preferences['inquiry_id']);
        $this->assertSame($doorModel->name, $record->preferences['door_model']);
        $this->assertSame('Vyřízení poptávky a příprava nabídky', $record->purpose);

        $this->assertNotNull($record->legal_document_id);
        $this->assertNotNull($record->data_processing_purpose_id);
    }

    /**
     * @return array{0: DoorModel, 1: DoorVariant}
     */
    private function catalogSelection(): array
    {
        $doorModel = DoorModel::query()->create([
            'name' => 'Testovací model '.uniqid(),
            'slug' => 'test-model-'.uniqid(),
            'category' => 'interierove',
            'base_price_without_vat' => 1000,
            'base_price_with_vat' => 1210,
            'active' => true,
        ]);

        $variant = DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'VAR-'.uniqid(),
            'width' => 800,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);

        $decor = Decor::query()->create([
            'name' => 'Testovací dekor '.uniqid(),
            'code' => 'DEC-'.uniqid(),
            'price_modifier' => 0,
            'active' => true,
        ]);

        $surcharge = Surcharge::query()->create([
            'name' => 'Testovací příplatek '.uniqid(),
            'code' => 'SUR-'.uniqid(),
            'price_without_vat' => 0,
            'active' => true,
        ]);

        $doorModel->decors()->attach($decor);
        $doorModel->surcharges()->attach($surcharge);

        return [$doorModel, $variant];
    }
}
