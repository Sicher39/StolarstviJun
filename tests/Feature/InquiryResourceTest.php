<?php

namespace Tests\Feature;

use App\Filament\Resources\InquiryResource;
use App\Filament\Resources\InquiryResource\Pages\ListInquiries;
use App\Models\Decor;
use App\Models\DoorFrame;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Hardware;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Surcharge;
use App\Services\Inquiry\InquiryItemBuilder;
use App\Services\Inquiry\InquiryItemSelectionResolver;
use App\Services\Inquiry\InquiryPreviewResolver;
use App\Services\Inquiry\InquirySnapshotPresenter;
use App\Services\Inquiry\InquiryStatus;
use App\Services\Inquiry\InquiryTotalsCalculator;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

        $this->assertSame(14, count($lines));
        $this->assertSame('Interiérové dveře Nord', $values['Model dveří']);
        $this->assertSame('PLNÉ-80 · 800 × 2100 mm', $values['Varianta']);
        $this->assertSame('falcové', $values['Typ otevírání']);
        $this->assertSame('levé', $values['Směr otevírání']);
        $this->assertSame('Dub přírodní (DEC-0001)', $values['Dekor']);
        $this->assertSame('Čiré (GLA-0001) · krytí 70 %', $values['Sklo']);
        $this->assertSame('Montáž', $values['Příplatky']);
        // The selection is described without amounts, the amounts follow in the breakdown of the price.
        $this->assertSame('1 200,00 Kč', $values['Základní cena dveří bez DPH']);
        $this->assertSame('250,00 Kč', $values['Varianta PLNÉ-80 bez DPH']);
        $this->assertSame('150,00 Kč', $values['Dekor Dub přírodní (DEC-0001) bez DPH']);
        $this->assertSame('100,00 Kč', $values['Sklo Čiré (GLA-0001) bez DPH']);
        $this->assertSame('50,00 Kč', $values['Příplatek Montáž (SUR-0001) bez DPH']);
        $this->assertSame('1 750,00 Kč', $values['Spočtená cena bez DPH']);
        $this->assertSame('2 117,50 Kč (21 %)', $values['Spočtená cena s DPH']);
    }

    public function test_the_price_breakdown_of_a_snapshot_explains_every_part_of_the_price_per_piece(): void
    {
        $breakdown = app(InquirySnapshotPresenter::class)->priceBreakdownLines($this->configuration());

        $this->assertSame([
            'Základní cena dveří bez DPH',
            'Varianta PLNÉ-80 bez DPH',
            'Dekor Dub přírodní (DEC-0001) bez DPH',
            'Sklo Čiré (GLA-0001) bez DPH',
            'Příplatek Montáž (SUR-0001) bez DPH',
        ], array_column($breakdown, 'label'));

        // A snapshot written before the breakdown existed is explained from the blocks it has always kept,
        // and the parts still add up to the price per piece of that door.
        $this->assertEquals(1750.0, $this->sumOfBreakdown($breakdown));
    }

    public function test_the_stored_breakdown_of_an_item_explains_the_price_that_was_charged(): void
    {
        $presenter = app(InquirySnapshotPresenter::class);

        $configuration = $this->configuration();
        $configuration['price_breakdown'] = [
            'vat_rate' => 21.0,
            'unit_price_without_vat' => 1450.0,
            'unit_price_with_vat' => 1754.5,
            'lines' => [
                ['key' => 'base_price', 'label' => 'Základní cena dveří', 'name' => null, 'code' => null, 'amount_without_vat' => 1200.0],
                ['key' => 'door_variant', 'label' => 'Varianta', 'name' => 'PLNÉ-80', 'code' => 'PLNÉ-80', 'amount_without_vat' => 0.0],
                ['key' => 'decor', 'label' => 'Dekor', 'name' => 'Dub přírodní', 'code' => 'DEC-0001', 'amount_without_vat' => 0.0],
                ['key' => 'glass_type', 'label' => 'Sklo', 'name' => 'Čiré', 'code' => 'GLA-0001', 'amount_without_vat' => 250.0],
            ],
        ];

        $values = array_column($presenter->priceBreakdownLines($configuration), 'value', 'label');

        $this->assertSame('1 200,00 Kč', $values['Základní cena dveří bez DPH']);
        // The stored amounts win over the blocks of the snapshot, so a later change of the catalog can not
        // rewrite what was charged.
        $this->assertSame('250,00 Kč', $values['Sklo Čiré (GLA-0001) bez DPH']);
        // A selected option that costs nothing is described in words instead of a zero column.
        $this->assertSame('bez příplatku', $values['Varianta PLNÉ-80 bez DPH']);
        $this->assertSame('bez příplatku', $values['Dekor Dub přírodní (DEC-0001) bez DPH']);
    }

    public function test_the_stored_price_of_an_item_is_compared_with_its_snapshot(): void
    {
        $presenter = app(InquirySnapshotPresenter::class);

        $configuration = $this->configuration();
        $configuration['totals'] = [
            'quantity' => 2,
            'unit_price_without_vat' => 1750.0,
            'price_without_vat' => 3500.0,
            'price_with_vat' => 4235.0,
            'vat_rate' => 21.0,
        ];

        // An item that was not recalculated needs no sentence, the snapshot speaks for itself.
        $this->assertNull($presenter->storedPriceComparison($configuration, [
            'quantity' => 2,
            'unit_price_without_vat' => '1750.00',
            'price_without_vat' => '3500.00',
            'price_with_vat' => '4235.00',
        ]));

        // A recalculated price is printed next to the original one, and the snapshot stays untouched.
        $this->assertSame(
            'Aktuálně uložená cena se liší od snapshotu: 1 800,00 Kč za kus (2 ks · 3 600,00 Kč bez DPH · 4 356,00 Kč s DPH)'
                .'; při odeslání to bylo 1 750,00 Kč za kus. Snapshot z odeslání zůstává nezměněný.',
            $presenter->storedPriceComparison($configuration, [
                'quantity' => 2,
                'unit_price_without_vat' => '1800.00',
                'price_without_vat' => '3600.00',
                'price_with_vat' => '4356.00',
            ]),
        );

        // Without a price per piece on one of the sides there is nothing to compare.
        $this->assertNull($presenter->storedPriceComparison(['door_model' => ['name' => 'Model X']], [
            'unit_price_without_vat' => 1000,
        ]));
        $this->assertNull($presenter->storedPriceComparison($configuration, []));
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
        $this->assertSame([], $presenter->summaryLines(null));
        $this->assertSame([], $presenter->itemLines(null));
        $this->assertSame([], $presenter->itemPriceLines(null));
        $this->assertSame([], $presenter->priceBreakdownLines(null));
        $this->assertNull($presenter->computedTotals(null));
        $this->assertNull($presenter->storedPriceComparison(null, ['unit_price_without_vat' => 1000]));
        $this->assertNull($presenter->computedTotals(['door_model' => ['name' => 'Model X']]));
    }

    public function test_the_recap_of_a_legacy_snapshot_counts_one_door_and_derives_the_vat_amount(): void
    {
        $summary = array_column(
            app(InquirySnapshotPresenter::class)->summaryLines($this->configuration()),
            'value',
            'label',
        );

        // The configurator of that time asked for one door only, so a stored snapshot without a summary is one piece.
        $this->assertSame('1 ks', $summary['Celkem dveří']);
        $this->assertSame('1 750,00 Kč', $summary['Cena bez DPH']);
        $this->assertSame('367,50 Kč', $summary['DPH']);
        $this->assertSame('2 117,50 Kč', $summary['Cena s DPH']);
        $this->assertSame('21 %', $summary['Sazba DPH']);
    }

    public function test_the_items_of_a_request_show_their_own_prices_and_the_recap_of_all_of_them(): void
    {
        $presenter = app(InquirySnapshotPresenter::class);
        $configuration = $this->multiItemConfiguration();

        $summary = array_column($presenter->summaryLines($configuration), 'value', 'label');

        $this->assertSame('3 ks', $summary['Celkem dveří']);
        $this->assertSame('5 050,00 Kč', $summary['Cena bez DPH']);
        $this->assertSame('1 060,50 Kč', $summary['DPH']);
        $this->assertSame('6 110,50 Kč', $summary['Cena s DPH']);
        $this->assertSame('21 %', $summary['Sazba DPH']);

        // The price per piece is always shown next to the line price of the same item.
        $secondItem = array_column($presenter->itemPriceLines($configuration['items'][1]), 'value', 'label');

        $this->assertSame('2 ks', $secondItem['Množství']);
        $this->assertSame('1 650,00 Kč', $secondItem['Cena za kus bez DPH']);
        $this->assertSame('3 300,00 Kč', $secondItem['Cena celkem bez DPH']);
        $this->assertSame('3 993,00 Kč', $secondItem['Cena celkem s DPH']);

        // The item that was sent without glass has no glass in its breakdown, so the list never pretends
        // that something was charged for it.
        $secondBreakdown = array_column($presenter->priceBreakdownLines($configuration['items'][1]), 'value', 'label');

        $this->assertArrayNotHasKey('Sklo Čiré (GLA-0001) bez DPH', $secondBreakdown);
        $this->assertEquals(1650.0, $this->sumOfBreakdown($presenter->priceBreakdownLines($configuration['items'][1])));

        $selection = array_column($presenter->itemLines($configuration['items'][0]), 'value', 'label');

        $this->assertSame('Interiérové dveře Nord', $selection['Model dveří']);
        $this->assertSame('Montáž', $selection['Příplatky']);

        // The single door view of the same snapshot keeps describing the first item with the order totals.
        $legacy = array_column($presenter->lines($configuration), 'value', 'label');

        $this->assertSame('5 050,00 Kč', $legacy['Spočtená cena bez DPH']);
        $this->assertSame('6 110,50 Kč (21 %)', $legacy['Spočtená cena s DPH']);
        $this->assertSame(
            'Konfigurátor spočítal 5 050,00 Kč bez DPH / 6 110,50 Kč s DPH (sazba DPH 21 %).',
            $presenter->computedTotals($configuration),
        );
    }

    public function test_the_number_of_doors_of_an_inquiry_is_summed_from_its_items(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $inquiry = $this->inquiry($doorModel, $variant);

        // An inquiry stored before the items existed describes one door.
        $this->assertSame(1, $inquiry->totalDoors());

        $this->item($inquiry, 'Dveře do ložnice', 2);
        $this->item($inquiry, 'Dveře do koupelny', 3);

        $this->assertSame(5, $inquiry->fresh()->totalDoors());
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

        // The accessories are stored on the items, so the single door view of the inquiry has no column
        // to bind them to and must not pretend it has one.
        $this->assertNotContains('door_frame_id', $fieldNames);
        $this->assertNotContains('hardware_id', $fieldNames);
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

    public function test_the_preview_of_an_item_uses_the_image_of_that_item(): void
    {
        Storage::fake(InquiryItem::PREVIEW_DISK, ['url' => 'http://localhost/storage']);

        [$doorModel, $variant] = $this->catalog();

        $item = $this->item($this->inquiry($doorModel, $variant));

        $item->addMediaFromString($this->pngBytes(300, 600))
            ->usingFileName('nahlad-polozky-'.$item->getKey().'.png')
            ->toMediaCollection(InquiryItem::PREVIEW_COLLECTION);

        $preview = app(InquiryPreviewResolver::class)->resolveItem($item);

        $this->assertNotNull($preview);
        $this->assertSame('Vygenerovaný náhled položky', $preview['label']);
        $this->assertStringContainsString('nahlad-polozky-', $preview['url']);
        $this->assertStringContainsString('expiration=', $preview['url']);
        $this->assertSame(InquiryItem::PREVIEW_DISK, $item->getFirstMedia(InquiryItem::PREVIEW_COLLECTION)?->disk);
    }

    public function test_the_inquiry_preview_falls_back_to_the_image_of_the_first_item(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);
        Storage::fake(InquiryItem::PREVIEW_DISK, ['url' => 'http://localhost/storage']);

        [$doorModel, $variant] = $this->catalog();

        $variant->addMediaFromString('reference')
            ->usingFileName('source-reference.png')
            ->toMediaCollection('source_reference');

        $inquiry = $this->inquiry($doorModel, $variant);
        $item = $this->item($inquiry);

        $item->addMediaFromString($this->pngBytes(300, 600))
            ->usingFileName('nahlad-polozky-'.$item->getKey().'.png')
            ->toMediaCollection(InquiryItem::PREVIEW_COLLECTION);

        $preview = app(InquiryPreviewResolver::class)->resolve($inquiry);

        $this->assertNotNull($preview);
        // The image of the item describes this very request, so it wins over the catalog reference image.
        $this->assertSame('Vygenerovaný náhled položky', $preview['label']);
        $this->assertStringContainsString('nahlad-polozky-', $preview['url']);
    }

    public function test_an_item_without_a_generated_image_has_no_preview(): void
    {
        $resolver = app(InquiryPreviewResolver::class);

        $this->assertNull($resolver->resolveItem(null));

        [$doorModel, $variant] = $this->catalog();

        $this->assertNull($resolver->resolveItem($this->item($this->inquiry($doorModel, $variant))));
    }

    public function test_the_doors_of_a_request_are_edited_in_one_repeater_per_item(): void
    {
        $repeater = null;

        foreach (InquiryResource::formFields()['items'] as $component) {
            if ($component instanceof Repeater) {
                $repeater = $component;
            }
        }

        $this->assertInstanceOf(Repeater::class, $repeater, 'Položky poptávky se upravují v repeateru, ne v jediném poli.');
        $this->assertSame('items', $repeater->getName());
        $this->assertSame('items', $repeater->getRelationshipName());
        $this->assertSame('position', $repeater->getOrderColumn());
        $this->assertSame(InquiryItem::MAX_ITEMS_PER_INQUIRY, $repeater->getMaxItems());
    }

    public function test_an_item_keeps_its_own_selection_mass_quantity_and_prices(): void
    {
        $fillable = (new InquiryItem)->getFillable();

        foreach ([
            'name',
            'quantity',
            'door_model_id',
            'door_variant_id',
            'decor_id',
            'glass_type_id',
            'door_frame_id',
            'hardware_id',
            'unit_price_without_vat',
            'price_without_vat',
            'price_with_vat',
        ] as $attribute) {
            $this->assertContains($attribute, $fillable, 'Položka poptávky nemůže uložit '.$attribute.'.');
        }

        // The snapshot is written by the configurator only and never becomes an editable field, while the
        // items repeater is part of the form.
        $fieldNames = [];

        foreach (InquiryResource::formFields() as $components) {
            foreach ($components as $component) {
                if ($component instanceof Field) {
                    $fieldNames[] = $component->getName();
                }
            }
        }

        $this->assertNotContains('configuration', $fieldNames);
        $this->assertContains('items', $fieldNames);
    }

    public function test_saving_recalculates_the_line_prices_and_the_totals_of_the_inquiry_from_its_items(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $inquiry = $this->inquiry($doorModel, $variant);

        $first = $inquiry->items()->create([
            'position' => 1,
            'name' => 'Dveře do ložnice',
            'quantity' => 1,
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'unit_price_without_vat' => 1750,
            'configuration' => $this->configuration(),
        ]);

        $second = $inquiry->items()->create([
            'position' => 2,
            'name' => 'Dveře do koupelny',
            'quantity' => 3,
            'unit_price_without_vat' => 1000,
            'configuration' => $this->configuration(),
        ]);

        app(InquiryTotalsCalculator::class)->recalculate($inquiry);

        $first->refresh();
        $second->refresh();
        $inquiry->refresh();

        // Every line is the price per piece multiplied by the quantity of that item.
        $this->assertEquals(1750.0, (float) $first->price_without_vat);
        $this->assertEquals(2117.5, (float) $first->price_with_vat);
        $this->assertEquals(3000.0, (float) $second->price_without_vat);
        $this->assertEquals(3630.0, (float) $second->price_with_vat);

        // The inquiry only mirrors their sum and the selection of the first door, so the readers of the
        // older one-piece shape keep seeing a consistent record.
        $this->assertEquals(4750.0, (float) $inquiry->price_without_vat);
        $this->assertEquals(5747.5, (float) $inquiry->price_with_vat);
        $this->assertSame($doorModel->id, (int) $inquiry->door_model_id);
        $this->assertSame($variant->id, (int) $inquiry->door_variant_id);
        $this->assertSame(4, $inquiry->totalDoors());

        // The snapshot of an item is the record of what the customer sent and is never rewritten.
        $this->assertSame($this->configuration(), $first->snapshot());
    }

    public function test_a_quantity_outside_the_documented_range_is_corrected_on_save(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $inquiry = $this->inquiry($doorModel, $variant);

        $item = $inquiry->items()->create([
            'position' => 1,
            'name' => 'Dveře do obývacího pokoje',
            'quantity' => 250,
            'unit_price_without_vat' => 1000,
            'configuration' => $this->configuration(),
        ]);

        app(InquiryTotalsCalculator::class)->recalculate($inquiry);

        $item->refresh();

        $this->assertSame(InquiryItem::MAX_QUANTITY_PER_ITEM, $item->quantity);
        $this->assertEquals(100000.0, (float) $item->price_without_vat);
    }

    public function test_an_inquiry_without_items_keeps_the_prices_that_were_typed_by_hand(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $inquiry = $this->inquiry($doorModel, $variant);

        app(InquiryTotalsCalculator::class)->recalculate($inquiry);

        $inquiry->refresh();

        $this->assertEquals(1750.0, (float) $inquiry->price_without_vat);
        $this->assertEquals(2117.5, (float) $inquiry->price_with_vat);

        $summary = app(InquiryTotalsCalculator::class)->summary($inquiry);

        $this->assertSame(1, $summary['total_doors']);
        $this->assertEquals(1750.0, $summary['price_without_vat']);
        $this->assertEquals(367.5, $summary['vat_amount']);
        $this->assertEquals(2117.5, $summary['price_with_vat']);
    }

    public function test_the_summary_of_a_multi_item_inquiry_is_summed_from_the_items(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $inquiry = $this->inquiry($doorModel, $variant);

        $inquiry->items()->create([
            'position' => 1,
            'name' => 'Dveře do ložnice',
            'quantity' => 1,
            'unit_price_without_vat' => 1750,
            'price_without_vat' => 1750,
            'price_with_vat' => 2117.5,
            'configuration' => $this->configuration(),
        ]);

        $inquiry->items()->create([
            'position' => 2,
            'name' => 'Dveře do koupelny',
            'quantity' => 2,
            'unit_price_without_vat' => 1350,
            'price_without_vat' => 2700,
            'price_with_vat' => 3267,
            'configuration' => $this->configuration(),
        ]);

        $summary = app(InquiryTotalsCalculator::class)->summary($inquiry->fresh());

        $this->assertSame(3, $summary['total_doors']);
        $this->assertEquals(4450.0, $summary['price_without_vat']);
        $this->assertEquals(934.5, $summary['vat_amount']);
        $this->assertEquals(5384.5, $summary['price_with_vat']);
    }

    public function test_the_live_sum_of_the_items_is_presented_like_the_recap_of_a_snapshot(): void
    {
        $lines = array_column(
            app(InquirySnapshotPresenter::class)->totalsLines([
                'total_doors' => 3,
                'price_without_vat' => 4250.0,
                'vat_amount' => 892.5,
                'price_with_vat' => 5142.5,
                'vat_rate' => 21.0,
            ]),
            'value',
            'label',
        );

        $this->assertSame('3 ks', $lines['Celkem dveří']);
        $this->assertSame('4 250,00 Kč', $lines['Cena bez DPH']);
        $this->assertSame('892,50 Kč', $lines['DPH']);
        $this->assertSame('5 142,50 Kč', $lines['Cena s DPH']);
        $this->assertSame('21 %', $lines['Sazba DPH']);
    }

    public function test_the_price_per_piece_is_resolved_from_the_catalog(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $decor = Decor::query()->create([
            'name' => 'Dekor '.uniqid(),
            'price_modifier' => 150,
            'active' => true,
        ]);

        $glassType = GlassType::query()->create([
            'name' => 'Sklo '.uniqid(),
            'price_modifier' => 100,
            'active' => true,
        ]);

        $surcharge = Surcharge::query()->create([
            'name' => 'Montáž '.uniqid(),
            'price_without_vat' => 50,
            'active' => true,
        ]);

        $doorModel->decors()->attach($decor->id);
        $doorModel->glasses()->attach($glassType->id);
        $doorModel->surcharges()->attach($surcharge->id);

        $builder = app(InquiryItemBuilder::class);

        // The documented formula: model + variant + decor + glass + surcharges.
        $this->assertEquals(1500.0, $builder->catalogUnitPrice(
            $doorModel->id,
            $variant->id,
            $decor->id,
            $glassType->id,
            [$surcharge->id],
        ));

        // Without the decor, the glass and the surcharge only the base price and the variant remain.
        $this->assertEquals(1200.0, $builder->catalogUnitPrice($doorModel->id, $variant->id, null, null));

        // A missing model or variant is refused instead of priced, so the form can ask for a correction.
        $this->assertNull($builder->catalogUnitPrice(null, $variant->id, null, null));
        $this->assertNull($builder->catalogUnitPrice($doorModel->id, null, null, null));
    }

    public function test_the_selection_of_a_stored_item_is_read_from_its_snapshot_when_the_columns_are_empty(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $inquiry = $this->inquiry($doorModel, $variant);

        $item = $inquiry->items()->create([
            'position' => 1,
            'name' => 'Dveře do ložnice',
            'quantity' => 1,
            'unit_price_without_vat' => 1750,
            'configuration' => [
                'door_model' => ['id' => $doorModel->id],
                'door_variant' => ['id' => $variant->id],
                'decor' => null,
                'glass_type' => null,
            ],
        ]);

        $fillData = app(InquiryItemSelectionResolver::class)->withSnapshotSelection($item->attributesToArray());

        $this->assertSame($doorModel->id, $fillData['door_model_id']);
        $this->assertSame($variant->id, $fillData['door_variant_id']);
        $this->assertNull($fillData['decor_id']);
        $this->assertNull($fillData['glass_type_id']);
    }

    public function test_a_snapshot_id_that_no_longer_exists_is_not_pushed_into_the_foreign_keys(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $inquiry = $this->inquiry($doorModel, $variant);

        $item = $inquiry->items()->create([
            'position' => 1,
            'name' => 'Dveře z neplatného snapshotu',
            'quantity' => 1,
            'unit_price_without_vat' => 1750,
            'configuration' => [
                // The model of the snapshot is gone and the decor row does not exist at all, so neither
                // may be pushed into the foreign key columns of the item.
                'door_model' => ['id' => 999999999],
                'door_variant' => ['id' => $variant->id],
                'decor' => ['id' => 999999998],
                'glass_type' => null,
            ],
        ]);

        $fillData = app(InquiryItemSelectionResolver::class)->withSnapshotSelection($item->attributesToArray());

        $this->assertNull($fillData['door_model_id']);
        $this->assertSame($variant->id, $fillData['door_variant_id']);
        $this->assertNull($fillData['decor_id']);
    }

    public function test_the_preview_of_an_edited_selection_uses_the_reference_image_of_the_variant(): void
    {
        Storage::fake('public', ['url' => 'http://localhost/storage']);

        [$doorModel, $variant] = $this->catalog();

        $variant->addMediaFromString('reference')
            ->usingFileName('source-reference.png')
            ->toMediaCollection('source_reference');

        $resolver = app(InquiryPreviewResolver::class);

        $preview = $resolver->resolveSelection($doorModel->id, $variant->id, null, null);

        $this->assertNotNull($preview);
        $this->assertStringContainsString('source-reference.png', $preview['url']);
        $this->assertSame('Referenční obrázek varianty', $preview['label']);
        $this->assertNull($resolver->resolveSelection(null, null, null, null));
    }

    public function test_the_item_form_offers_the_frame_and_the_hardware_as_optional_selects(): void
    {
        $fields = [];

        foreach (InquiryResource::itemFields() as $component) {
            if ($component instanceof Field) {
                $fields[$component->getName()] = $component;
            }
        }

        foreach (['door_frame_id', 'hardware_id'] as $fieldName) {
            $this->assertArrayHasKey($fieldName, $fields, 'Položka poptávky nenabízí pole '.$fieldName.'.');
            $this->assertInstanceOf(Select::class, $fields[$fieldName], 'Pole '.$fieldName.' se vybírá z katalogu.');
            // Both accessories are optional, exactly like in the configurator.
            $this->assertFalse($fields[$fieldName]->isRequired());
        }
    }

    public function test_the_accessory_selects_offer_only_the_active_accessories_of_the_model(): void
    {
        [$doorModel] = $this->catalog();
        [$otherModel] = $this->catalog();

        $frame = DoorFrame::factory()->pricedAt(150)->create();
        $frame->doorModels()->attach($doorModel);

        $inactiveFrame = DoorFrame::factory()->inactive()->create();
        $inactiveFrame->doorModels()->attach($doorModel);

        $foreignFrame = DoorFrame::factory()->create();
        $foreignFrame->doorModels()->attach($otherModel);

        $hardware = Hardware::factory()->pricedAt(300)->create();
        $hardware->doorModels()->attach($doorModel);

        $frameOptions = InquiryResource::doorFrameOptions($doorModel->id, null);

        // Only the active frame of this very model is offered; the inactive and the foreign row are not.
        $this->assertSame([$frame->id], array_keys($frameOptions));
        $this->assertSame($frame->name, $frameOptions[$frame->id]);
        $this->assertSame([$hardware->id], array_keys(InquiryResource::hardwareOptions($doorModel->id, null)));

        // A stored value stays selectable, so the label of a retired row is still readable; the rule of the
        // field refuses to save it again instead of letting the select drop it silently.
        $withStoredValue = InquiryResource::doorFrameOptions($doorModel->id, $foreignFrame->id);

        $this->assertSame($foreignFrame->name.' (mimo aktuální nabídku)', $withStoredValue[$foreignFrame->id]);

        // Without a model the whole active catalog is offered, so a stored value is never lost before a
        // model is picked.
        $this->assertEqualsCanonicalizing(
            [$frame->id, $foreignFrame->id],
            array_keys(InquiryResource::doorFrameOptions(null, null)),
        );
    }

    public function test_the_accessories_of_a_stored_item_are_read_from_its_snapshot_when_the_columns_are_empty(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $frame = DoorFrame::factory()->create();
        $frame->doorModels()->attach($doorModel);

        $hardware = Hardware::factory()->create();
        $hardware->doorModels()->attach($doorModel);

        $item = $this->inquiry($doorModel, $variant)->items()->create([
            'position' => 1,
            'name' => 'Dveře se zárubní',
            'quantity' => 1,
            'unit_price_without_vat' => 1650,
            'configuration' => [
                'door_model' => ['id' => $doorModel->id],
                'door_variant' => ['id' => $variant->id],
                'door_frame' => ['id' => $frame->id, 'name' => $frame->name],
                'hardware' => ['id' => $hardware->id, 'name' => $hardware->name],
            ],
        ]);

        $fillData = app(InquiryItemSelectionResolver::class)->withSnapshotSelection($item->attributesToArray());

        $this->assertSame($frame->id, $fillData['door_frame_id']);
        $this->assertSame($hardware->id, $fillData['hardware_id']);

        // Once the model stops offering the frame, the snapshot id may not be pushed into the foreign key
        // column: the item form would refuse the pair and the item could never be saved again.
        $frame->doorModels()->detach($doorModel);

        $fillData = app(InquiryItemSelectionResolver::class)->withSnapshotSelection($item->fresh()->attributesToArray());

        $this->assertNull($fillData['door_frame_id']);
        $this->assertSame($hardware->id, $fillData['hardware_id']);
    }

    public function test_the_price_per_piece_of_a_selection_is_priced_with_the_accessories_of_the_catalog(): void
    {
        [$doorModel, $variant] = $this->catalog();
        [$otherModel] = $this->catalog();

        $frame = DoorFrame::factory()->pricedAt(150)->create();
        $frame->doorModels()->attach($doorModel);

        $hardware = Hardware::factory()->pricedAt(300)->create();
        $hardware->doorModels()->attach($doorModel);

        $foreignHardware = Hardware::factory()->pricedAt(999)->create();
        $foreignHardware->doorModels()->attach($otherModel);

        $builder = app(InquiryItemBuilder::class);

        // 1000 base + 200 variant + 150 frame + 300 hardware, all read from the catalog.
        $this->assertEquals(1650.0, $builder->catalogUnitPrice(
            $doorModel->id,
            $variant->id,
            null,
            null,
            [],
            $frame->id,
            $hardware->id,
        ));

        // An accessory of another model adds nothing, so a recalculation can never price something the
        // catalog does not offer for the selection.
        $this->assertEquals(1200.0, $builder->catalogUnitPrice(
            $doorModel->id,
            $variant->id,
            null,
            null,
            [],
            null,
            $foreignHardware->id,
        ));
    }

    public function test_the_accessories_of_a_request_are_labelled_for_the_list_table(): void
    {
        [$doorModel, $variant] = $this->catalog();

        $frame = DoorFrame::factory()->create(['name' => 'Bezfalcová '.uniqid()]);
        $frame->doorModels()->attach($doorModel);

        $hardware = Hardware::factory()->create(['name' => 'Klika '.uniqid()]);
        $hardware->doorModels()->attach($doorModel);

        $inquiry = $this->inquiry($doorModel, $variant);
        $this->item($inquiry)->forceFill([
            'door_frame_id' => $frame->id,
            'hardware_id' => $hardware->id,
        ])->save();

        $this->assertSame(
            'Zárubeň: '.$frame->name.' · Kování: '.$hardware->name,
            InquiryResource::accessoryLabels($inquiry->fresh()),
        );

        // An inquiry stored before the accessory columns existed keeps the selection in its snapshot.
        $legacy = $this->inquiry($doorModel, $variant);
        $legacy->forceFill(['configuration' => [
            ...$this->configuration(),
            'door_frame' => ['id' => 42, 'name' => 'Obložková', 'code' => 'FRM-0042'],
            'hardware' => ['id' => 43, 'name' => 'Klika R', 'code' => 'HWD-0043'],
        ]])->save();

        $this->assertSame('Zárubeň: Obložková · Kování: Klika R', InquiryResource::accessoryLabels($legacy->fresh()));

        // A request without either of them shows nothing at all instead of an invented selection.
        $this->assertNull(InquiryResource::accessoryLabels($this->inquiry($doorModel, $variant)));
        $this->assertNull(InquiryResource::accessoryLabels(null));
    }

    /**
     * The sum of the amounts of a price breakdown, parsed back from the formatted values of the presenter.
     *
     * @param  list<array{label: string, value: string}>  $breakdown
     */
    private function sumOfBreakdown(array $breakdown): float
    {
        $sum = 0.0;

        foreach ($breakdown as $line) {
            $sum += (float) str_replace([' ', 'Kč', ','], ['', '', '.'], $line['value']);
        }

        return round($sum, 2);
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
     * One configured door of the request, stored the way ConfiguratorInquiryController writes it.
     */
    private function item(Inquiry $inquiry, string $name = 'Dveře do ložnice', int $quantity = 1): InquiryItem
    {
        return $inquiry->items()->create([
            'position' => 0,
            'name' => $name,
            'quantity' => $quantity,
            'unit_price_without_vat' => 1750,
            'price_without_vat' => 1750 * $quantity,
            'price_with_vat' => round(1750 * $quantity * 1.21, 2),
            'configuration' => $this->configuration(),
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

    /**
     * A request with two separately configured doors, in the shape the controller stores it: the items
     * carry their own prices, the summary carries the recap of the whole request and the single door view
     * on the top level describes the first item.
     *
     * Neither item carries a `price_breakdown`, which is exactly how an inquiry sent before the breakdown
     * existed looks: the presenter has to explain its price from the model, the variant, the decor, the
     * glass and the surcharges that the snapshot has always kept.
     *
     * @return array<string, mixed>
     */
    private function multiItemConfiguration(): array
    {
        $firstItem = $this->configuration();
        $firstItem['name'] = 'Dveře do ložnice';
        $firstItem['quantity'] = 1;
        $firstItem['totals'] = [
            'quantity' => 1,
            'unit_price_without_vat' => 1750.0,
            'price_without_vat' => 1750.0,
            'price_with_vat' => 2117.5,
            'vat_rate' => 21.0,
        ];

        $secondItem = $this->configuration();
        $secondItem['name'] = 'Dveře do koupelny';
        $secondItem['quantity'] = 2;
        $secondItem['glass_type'] = null;
        // Without the glass only the base price, the variant, the decor and the surcharge remain.
        $secondItem['totals'] = [
            'quantity' => 2,
            'unit_price_without_vat' => 1650.0,
            'price_without_vat' => 3300.0,
            'price_with_vat' => 3993.0,
            'vat_rate' => 21.0,
        ];

        return [
            'items' => [$firstItem, $secondItem],
            'summary' => [
                'total_doors' => 3,
                'price_without_vat' => 5050.0,
                'vat_amount' => 1060.5,
                'price_with_vat' => 6110.5,
                'vat_rate' => 21.0,
            ],
            'door_model' => $firstItem['door_model'],
            'door_variant' => $firstItem['door_variant'],
            'decor' => $firstItem['decor'],
            'glass_type' => $firstItem['glass_type'],
            'surcharges' => $firstItem['surcharges'],
            'totals' => [
                'price_without_vat' => 5050.0,
                'price_with_vat' => 6110.5,
                'vat_rate' => 21.0,
            ],
        ];
    }
}
