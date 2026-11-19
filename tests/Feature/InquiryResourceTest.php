<?php

namespace Tests\Feature;

use App\Filament\Resources\InquiryResource;
use App\Filament\Resources\InquiryResource\Pages\ListInquiries;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\Inquiry;
use App\Services\Inquiry\InquiryPreviewResolver;
use App\Services\Inquiry\InquirySnapshotPresenter;
use App\Services\Inquiry\InquiryStatus;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The two column detail of an inquiry: editable contact and configuration fields, the snapshot shown
 * as readable text instead of raw JSON, and the best available image of the chosen door.
 *
 * The rendered panel page is not asserted here, only the services behind it, the form definition and
 * the status vocabulary of the list tabs.
 */
class InquiryResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_snapshot_is_presented_as_readable_lines(): void
    {
        $lines = app(InquirySnapshotPresenter::class)->lines($this->configuration());

        $values = array_column($lines, 'value', 'label');

        $this->assertSame(13, count($lines));
        $this->assertSame('Interiérové dveře Nord', $values['Model dveří']);
        $this->assertSame('PLNÉ-80 · 800 × 2100 mm', $values['Varianta']);
        $this->assertSame('falcové', $values['Typ otevírání']);
        $this->assertSame('levé', $values['Směr otevírání']);
        $this->assertSame('Dub přírodní (DEC-0001)', $values['Dekor']);
        $this->assertSame('Čiré (GLA-0001) · krytí 70 %', $values['Sklo']);
        $this->assertSame('Montáž (50,00 Kč)', $values['Příplatky']);
        $this->assertSame('1 200,00 Kč', $values['Základní cena modelu bez DPH']);
        $this->assertSame('250,00 Kč', $values['Příplatek varianty bez DPH']);
        $this->assertSame('150,00 Kč', $values['Příplatek dekoru bez DPH']);
        $this->assertSame('100,00 Kč', $values['Příplatek skla bez DPH']);
        $this->assertSame('1 750,00 Kč', $values['Spočtená cena bez DPH']);
        $this->assertSame('2 117,50 Kč (21 %)', $values['Spočtená cena s DPH']);
    }

    public function test_the_totals_of_the_snapshot_are_summarised_for_the_price_section(): void
    {
        $presenter = app(InquirySnapshotPresenter::class);

        $this->assertSame(
            'Konfigurátor spočítal 1 750,00 Kč bez DPH / 2 117,50 Kč s DPH (sazba DPH 21 %).',
            $presenter->computedTotals($this->configuration()),
        );
    }

    public function test_a_snapshot_without_decor_glass_and_surcharges_stays_readable(): void
    {
        $lines = app(InquirySnapshotPresenter::class)->lines([
            'door_model' => ['name' => 'Model X'],
            'door_variant' => ['code' => 'VAR-1'],
            'decor' => null,
            'glass_type' => null,
            'surcharges' => [],
            'totals' => [],
        ]);

        $this->assertSame([
            ['label' => 'Model dveří', 'value' => 'Model X'],
            ['label' => 'Varianta', 'value' => 'VAR-1'],
        ], $lines);
    }

    public function test_an_inquiry_without_a_snapshot_has_no_lines_and_no_totals(): void
    {
        $presenter = app(InquirySnapshotPresenter::class);

        $this->assertSame([], $presenter->lines(null));
        $this->assertSame([], $presenter->lines([]));
        $this->assertNull($presenter->computedTotals(null));
        $this->assertNull($presenter->computedTotals(['door_model' => ['name' => 'Model X']]));
    }

    public function test_the_record_stays_editable_and_the_raw_json_is_gone(): void
    {
        $fieldNames = [];

        foreach (InquiryResource::formFields() as $components) {
            foreach ($components as $component) {
                if ($component instanceof Field) {
                    $fieldNames[] = $component->getName();
                }
            }
        }

        foreach ([
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_message',
            'status',
            'door_model_id',
            'door_variant_id',
            'decor_id',
            'glass_type_id',
            'surcharges',
            'price_without_vat',
            'price_with_vat',
        ] as $fieldName) {
            $this->assertContains($fieldName, $fieldNames, 'Ve formuláři chybí pole '.$fieldName.'.');
        }

        // The snapshots stay in the database, they are just never typed by hand again.
        $this->assertNotContains('configuration', $fieldNames);
        $this->assertNotContains('crm_payload', $fieldNames);
    }

    public function test_the_status_field_offers_only_the_four_internal_statuses(): void
    {
        $statusField = null;

        foreach (InquiryResource::formFields()['status'] as $component) {
            if ($component instanceof Select) {
                $statusField = $component;
            }
        }

        $this->assertInstanceOf(Select::class, $statusField, 'Stav poptávky se vybírá ze seznamu, ne volným textem.');
        $this->assertSame('status', $statusField->getName());
        $this->assertTrue($statusField->isRequired());
        $this->assertSame(InquiryStatus::options(), $statusField->getOptions());
        $this->assertSame(InquiryStatus::New->value, $statusField->getDefaultState());
    }

    public function test_the_status_vocabulary_keeps_the_keys_of_stored_rows(): void
    {
        $this->assertSame([
            'new' => 'Nová',
            'in_progress' => 'Rozpracovaná',
            'approved' => 'Schválená',
            'rejected' => 'Odmítnutá',
        ], InquiryStatus::options());

        // The configurator and the column default write "new", so that key must never change.
        $this->assertSame('new', InquiryStatus::New->value);

        // A value outside the vocabulary stays recognisable instead of blowing up on an old row.
        $this->assertNull(InquiryStatus::tryFrom('kontaktován'));
    }

    public function test_the_list_tabs_split_the_inquiries_by_status(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $newInquiry = $this->inquiry($doorModel, $variant);
        $approvedInquiry = $this->inquiry($doorModel, $variant, InquiryStatus::Approved);

        $tabs = app(ListInquiries::class)->getTabs();

        $this->assertSame(['all', 'new', 'in_progress', 'approved', 'rejected'], array_keys($tabs));
        $this->assertSame('Vše', (string) $tabs['all']->getLabel());
        $this->assertSame('Schválená', (string) $tabs['approved']->getLabel());

        // "Vše" is unfiltered, every other tab scopes the query to its own status.
        $this->assertTrue($tabs['all']->modifyQuery(Inquiry::query())->whereKey($newInquiry->getKey())->exists());
        $this->assertTrue($tabs['all']->modifyQuery(Inquiry::query())->whereKey($approvedInquiry->getKey())->exists());
        $this->assertTrue($tabs['new']->modifyQuery(Inquiry::query())->whereKey($newInquiry->getKey())->exists());
        $this->assertTrue($tabs['approved']->modifyQuery(Inquiry::query())->whereKey($approvedInquiry->getKey())->exists());
        $this->assertFalse($tabs['approved']->modifyQuery(Inquiry::query())->whereKey($newInquiry->getKey())->exists());
    }

    public function test_the_preview_uses_the_reference_image_of_the_variant(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        [$doorModel, $variant] = $this->catalog();

        $variant->addMediaFromString('reference')
            ->usingFileName('source-reference.png')
            ->toMediaCollection('source_reference');

        $preview = app(InquiryPreviewResolver::class)->resolve($this->inquiry($doorModel, $variant));

        $this->assertNotNull($preview);
        $this->assertStringContainsString('source-reference.png', $preview['url']);
        $this->assertSame('Referenční obrázek varianty', $preview['label']);
    }

    public function test_the_preview_falls_back_to_the_photo_of_the_model(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        [$doorModel, $variant] = $this->catalog();

        $doorModel->addMediaFromString('photo')
            ->usingFileName('model-preview.png')
            ->toMediaCollection('preview_image');

        $preview = app(InquiryPreviewResolver::class)->resolve($this->inquiry($doorModel, $variant));

        $this->assertNotNull($preview);
        $this->assertStringContainsString('model-preview.png', $preview['url']);
        $this->assertSame('Náhledová fotografie modelu', $preview['label']);
    }

    public function test_the_preview_is_empty_when_the_catalog_has_no_image(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $resolver = app(InquiryPreviewResolver::class);

        $this->assertNull($resolver->resolve($this->inquiry($doorModel, $variant)));
        $this->assertNull($resolver->resolve(null));
    }

    public function test_the_preview_prefers_the_generated_image_of_the_private_disk(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);
        Storage::fake(Inquiry::PREVIEW_DISK, ['url' => 'http://localhost/storage']);

        [$doorModel, $variant] = $this->catalog();

        // Even with a catalog reference image available, the illustration of this very inquiry wins.
        $variant->addMediaFromString('reference')
            ->usingFileName('source-reference.png')
            ->toMediaCollection('source_reference');

        $inquiry = $this->inquiry($doorModel, $variant);

        $inquiry->addMediaFromString($this->pngBytes(300, 600))
            ->usingFileName('nahlad-poptavky-'.$inquiry->getKey().'.png')
            ->toMediaCollection(Inquiry::PREVIEW_COLLECTION);

        $preview = app(InquiryPreviewResolver::class)->resolve($inquiry);

        $this->assertNotNull($preview);
        $this->assertSame('Vygenerovaný náhled konfigurace', $preview['label']);
        $this->assertStringContainsString('nahlad-poptavky-', $preview['url']);
        // The private disk is never reachable without a signature, so the administration gets a temporary
        // URL instead of a plain path under the public storage symlink.
        $this->assertStringContainsString('expiration=', $preview['url']);
        $this->assertSame(Inquiry::PREVIEW_DISK, $inquiry->getFirstMedia(Inquiry::PREVIEW_COLLECTION)?->disk);
    }

    /**
     * @return array{0: DoorModel, 1: DoorVariant}
     */
    private function catalog(): array
    {
        $doorModel = DoorModel::query()->create([
            'name' => 'Test model '.uniqid(),
            'slug' => 'test-model-'.uniqid(),
            'category' => 'interierove',
            'base_price_without_vat' => 1000,
            'active' => true,
        ]);

        $variant = DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'VAR-'.uniqid(),
            'width' => 800,
            'height' => 2100,
            'has_glass' => true,
            'sliding_possible' => false,
            'price_modifier' => 200,
        ]);

        return [$doorModel, $variant];
    }

    private function inquiry(DoorModel $doorModel, DoorVariant $variant, InquiryStatus $status = InquiryStatus::New): Inquiry
    {
        return Inquiry::query()->create([
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'customer_name' => 'Jan Novák',
            'customer_email' => 'jan.novak@example.test',
            'price_without_vat' => 1750,
            'price_with_vat' => 2117.5,
            'configuration' => $this->configuration(),
            'status' => $status->value,
        ]);
    }

    /**
     * A minimal valid PNG assembled with zlib only, so the test does not depend on the GD extension.
     */
    private function pngBytes(int $width, int $height): string
    {
        $raw = '';

        for ($row = 0; $row < $height; $row++) {
            $raw .= "\x00".str_repeat("\x00", $width * 3);
        }

        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw))
            .$chunk('IEND', '');
    }

    /**
     * The snapshot as ConfiguratorInquiryController writes it, with the plain prices of the catalog.
     *
     * @return array<string, mixed>
     */
    private function configuration(): array
    {
        return [
            'door_model' => [
                'id' => 1,
                'name' => 'Interiérové dveře Nord',
                'slug' => 'interierove-dvere-nord',
                'category' => 'interiérové',
                'base_price_without_vat' => 1200.0,
            ],
            'door_variant' => [
                'id' => 2,
                'code' => 'PLNÉ-80',
                'width' => 800,
                'height' => 2100,
                'opening_direction' => 'levé',
                'opening_type' => 'falcové',
                'has_glass' => true,
                'sliding_possible' => false,
                'price_modifier' => 250.0,
            ],
            'decor' => [
                'id' => 3,
                'name' => 'Dub přírodní',
                'code' => 'DEC-0001',
                'price_modifier' => 150.0,
            ],
            'glass_type' => [
                'id' => 4,
                'name' => 'Čiré',
                'code' => 'GLA-0001',
                'opacity' => 70,
                'price_modifier' => 100.0,
            ],
            'surcharges' => [
                [
                    'id' => 5,
                    'name' => 'Montáž',
                    'code' => 'SUR-0001',
                    'price_without_vat' => 50.0,
                ],
            ],
            'totals' => [
                'price_without_vat' => 1750.0,
                'price_with_vat' => 2117.5,
                'vat_rate' => 21.0,
            ],
        ];
    }
}
