<?php

namespace Tests\Feature;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Inquiry;
use App\Models\InquiryItem;
use App\Models\Surcharge;
use App\Services\Inquiry\InquiryStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfiguratorInquiryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_configurator_inquiry_can_be_stored_with_compatible_selection(): void
    {
        [$doorModel, $variant, $decor, $glassType, $surcharge] = $this->createCompatibleCatalogSelection();

        $response = $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'decor_id' => $decor->id,
            'glass_type_id' => $glassType->id,
            'surcharge_ids' => [$surcharge->id],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'customer_phone' => '+420 123 456 789',
            'customer_message' => 'Prosím o ověření konfigurace.',
            // The acknowledgement of the privacy policy is required by the endpoint.
            'gdpr_consent' => '1',
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas(Inquiry::class, [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'decor_id' => $decor->id,
            'glass_type_id' => $glassType->id,
            'customer_email' => 'jan@example.com',
            'price_without_vat' => 1550,
            'price_with_vat' => 1875.5,
            'status' => 'new',
        ]);

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();

        $this->assertDatabaseHas('inquiry_surcharge', [
            'inquiry_id' => $inquiry->id,
            'surcharge_id' => $surcharge->id,
            'price_without_vat' => 50,
        ]);

        // The flat one piece payload of the older configurator still becomes exactly one item, so an inquiry
        // sent that way reads the same as a request that was built from items.
        $item = $inquiry->items()->firstOrFail();

        $this->assertSame(1, $item->quantity);
        $this->assertSame($doorModel->name.' · '.$variant->code, $item->name);
        $this->assertEquals(1550.0, (float) $item->unit_price_without_vat);
        $this->assertEquals(1550.0, (float) $item->price_without_vat);
        $this->assertEquals(1875.5, (float) $item->price_with_vat);
        $this->assertCount(1, $inquiry->configuration['items']);
        $this->assertSame(1, $inquiry->configuration['summary']['total_doors']);
    }

    public function test_configurator_inquiry_uses_the_configured_vat_rate(): void
    {
        config(['pricing.vat_rate' => 12]);

        [$doorModel, $variant, $decor, $glassType, $surcharge] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'decor_id' => $decor->id,
            'glass_type_id' => $glassType->id,
            'surcharge_ids' => [$surcharge->id],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas(Inquiry::class, [
            'customer_email' => 'jan@example.com',
            'price_without_vat' => 1550,
            'price_with_vat' => 1736,
        ]);

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();

        $this->assertEquals(12.0, (float) $inquiry->configuration['totals']['vat_rate']);
    }

    public function test_configurator_inquiry_rejects_variant_from_different_model(): void
    {
        [$doorModel] = $this->createCompatibleCatalogSelection();

        $otherModel = DoorModel::query()->create([
            'name' => 'Other test model',
            'slug' => 'other-test-model-'.uniqid(),
            'base_price_without_vat' => 2000,
            'base_price_with_vat' => 2420,
            'active' => true,
        ]);

        $otherVariant = DoorVariant::query()->create([
            'door_model_id' => $otherModel->id,
            'code' => 'OTHER',
            'width' => 800,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);

        $response = $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $otherVariant->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'surcharge_ids' => [],
            'gdpr_consent' => '1',
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasErrors('door_variant_id');
    }

    public function test_configurator_inquiry_rejects_glass_for_non_glass_variant(): void
    {
        [$doorModel, , , $glassType] = $this->createCompatibleCatalogSelection();

        $variantWithoutGlass = DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'NO-GLASS-'.uniqid(),
            'width' => 900,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);

        $response = $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variantWithoutGlass->id,
            'glass_type_id' => $glassType->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'surcharge_ids' => [],
            'gdpr_consent' => '1',
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasErrors('glass_type_id');
    }

    public function test_configurator_inquiry_stores_the_generated_preview_png(): void
    {
        Storage::fake('local');

        [$doorModel, $variant, $decor, $glassType, $surcharge] = $this->createCompatibleCatalogSelection();

        $response = $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'decor_id' => $decor->id,
            'glass_type_id' => $glassType->id,
            'surcharge_ids' => [$surcharge->id],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
            'door_preview' => UploadedFile::fake()->createWithContent('nahlad-konfigurace.png', $this->pngBytes(300, 600)),
            // A visitor can not push a media path, a URL or a price into the stored configuration.
            'door_preview_url' => 'https://example.invalid/foreign.png',
            'price_with_vat' => 1,
            'configuration' => ['totals' => ['price_with_vat' => 1]],
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();
        $media = $inquiry->getFirstMedia(Inquiry::PREVIEW_COLLECTION);

        $this->assertNotNull($media, 'Vygenerovaný náhled se k poptávce neuložil.');
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame('nahlad-poptavky-'.$inquiry->getKey().'.png', $media->file_name);
        $this->assertSame(Inquiry::PREVIEW_DISK, $media->disk);
        Storage::disk(Inquiry::PREVIEW_DISK)->assertExists($media->getPathRelativeToRoot());

        // The price and the snapshot stay authoritative: the upload can not change either of them.
        $this->assertEquals(1875.5, (float) $inquiry->price_with_vat);
        $this->assertEquals(1875.5, (float) $inquiry->configuration['totals']['price_with_vat']);
        $this->assertArrayNotHasKey('door_preview', $inquiry->configuration);
        $this->assertArrayNotHasKey('door_preview_url', $inquiry->configuration);
        $this->assertStringNotContainsString('example.invalid', (string) json_encode($inquiry->configuration));
    }

    public function test_configurator_inquiry_rejects_an_svg_preview(): void
    {
        Storage::fake('local');

        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="300" height="600"><rect width="300" height="600" /></svg>';

        $response = $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'surcharge_ids' => [],
            'gdpr_consent' => '1',
            'door_preview' => UploadedFile::fake()
                ->createWithContent('nahlad-konfigurace.svg', $svg)
                ->mimeType('image/svg+xml'),
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasErrors('door_preview');

        // A refused upload must not leave a half stored inquiry behind.
        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
    }

    public function test_configurator_inquiry_rejects_a_preview_with_implausible_dimensions(): void
    {
        Storage::fake('local');

        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $response = $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'surcharge_ids' => [],
            'gdpr_consent' => '1',
            // A valid PNG, but far too small to be a composed door.
            'door_preview' => UploadedFile::fake()->createWithContent('nahlad-konfigurace.png', $this->pngBytes(16, 16)),
        ]);

        $response
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasErrors('door_preview');

        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
    }

    public function test_configurator_inquiry_can_be_stored_without_a_generated_preview(): void
    {
        Storage::fake('local');

        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'surcharge_ids' => [],
            'gdpr_consent' => '1',
        ])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();

        // An inquiry sent without an illustration still exists; the administration falls back to the catalog.
        $this->assertFalse($inquiry->hasMedia(Inquiry::PREVIEW_COLLECTION));
        $this->assertSame(InquiryStatus::New->value, $inquiry->status);
    }

    /**
     * A request may describe several separately configured doors. Each of them keeps its own name, quantity
     * and price per piece, and the inquiry carries the recap of all of them.
     */
    public function test_a_request_with_several_items_stores_one_row_and_one_price_per_item(): void
    {
        [$doorModel, $variant, $decor, $glassType, $surcharge] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'items' => [
                [
                    'name' => 'Dveře do ložnice',
                    'quantity' => 1,
                    'door_model_id' => $doorModel->id,
                    'door_variant_id' => $variant->id,
                    'decor_id' => $decor->id,
                    'glass_type_id' => $glassType->id,
                    'surcharge_ids' => [$surcharge->id],
                ],
                [
                    'name' => 'Dveře do koupelny',
                    'quantity' => 2,
                    'door_model_id' => $doorModel->id,
                    'door_variant_id' => $variant->id,
                    'decor_id' => $decor->id,
                    'surcharge_ids' => [$surcharge->id],
                ],
            ],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();
        $items = $inquiry->items()->get();

        $this->assertCount(2, $items);
        $this->assertSame(['Dveře do ložnice', 'Dveře do koupelny'], $items->pluck('name')->all());
        $this->assertSame([1, 2], $items->pluck('quantity')->all());

        // The first item is priced with the glass, the second one without it.
        $this->assertEquals(1550.0, (float) $items[0]->unit_price_without_vat);
        $this->assertEquals(1550.0, (float) $items[0]->price_without_vat);
        $this->assertEquals(1350.0, (float) $items[1]->unit_price_without_vat);
        $this->assertEquals(2700.0, (float) $items[1]->price_without_vat);
        $this->assertEquals(3267.0, (float) $items[1]->price_with_vat);

        // The inquiry keeps the totals of the whole request, so the older readers stay consistent.
        $this->assertEquals(4250.0, (float) $inquiry->price_without_vat);
        $this->assertEquals(5142.5, (float) $inquiry->price_with_vat);

        $summary = $inquiry->configuration['summary'];

        $this->assertSame(3, $summary['total_doors']);
        $this->assertSame(2, count($inquiry->configuration['items']));
        $this->assertEquals(4250.0, (float) $summary['price_without_vat']);
        $this->assertEquals(892.5, (float) $summary['vat_amount']);
        $this->assertEquals(5142.5, (float) $summary['price_with_vat']);
        $this->assertEquals(21.0, (float) $summary['vat_rate']);

        // The single door view on the top level still describes the first item.
        $this->assertSame($doorModel->name, $inquiry->configuration['door_model']['name']);
        $this->assertSame($variant->code, $inquiry->configuration['door_variant']['code']);

        // The same surcharge appears in both items, but the pivot has one row per surcharge.
        $this->assertSame(1, DB::table('inquiry_surcharge')->where('inquiry_id', $inquiry->id)->count());
    }

    public function test_each_item_of_the_request_keeps_its_own_generated_preview(): void
    {
        Storage::fake('local');

        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'items' => [
                [
                    'name' => 'První dveře',
                    'quantity' => 1,
                    'door_model_id' => $doorModel->id,
                    'door_variant_id' => $variant->id,
                    'surcharge_ids' => [],
                    'door_preview' => UploadedFile::fake()->createWithContent('nahlad-1.png', $this->pngBytes(300, 600)),
                ],
                [
                    'name' => 'Druhé dveře',
                    'quantity' => 3,
                    'door_model_id' => $doorModel->id,
                    'door_variant_id' => $variant->id,
                    'surcharge_ids' => [],
                    'door_preview' => UploadedFile::fake()->createWithContent('nahlad-2.png', $this->pngBytes(320, 640)),
                ],
            ],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();
        $items = $inquiry->items()->get();

        foreach ($items as $item) {
            $media = $item->getFirstMedia(InquiryItem::PREVIEW_COLLECTION);

            $this->assertNotNull($media, 'Položce '.$item->getKey().' chybí vygenerovaný náhled.');
            $this->assertSame('image/png', $media->mime_type);
            $this->assertSame('nahlad-polozky-'.$item->getKey().'.png', $media->file_name);
            $this->assertSame(InquiryItem::PREVIEW_DISK, $media->disk);
            Storage::disk(InquiryItem::PREVIEW_DISK)->assertExists($media->getPathRelativeToRoot());
        }

        // The item payload keeps the inquiry itself free of the illustration.
        $this->assertFalse($inquiry->hasMedia(Inquiry::PREVIEW_COLLECTION));
    }

    public function test_the_quantity_of_an_item_is_required_and_limited(): void
    {
        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $basePayload = [
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ];

        $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            ...$basePayload,
            'items' => [[
                'quantity' => 0,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
            ]],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            ...$basePayload,
            'items' => [[
                'quantity' => InquiryItem::MAX_QUANTITY_PER_ITEM + 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
            ]],
        ])->assertSessionHasErrors('items.0.quantity');

        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
    }

    /**
     * The limit is inclusive: the highest allowed quantity is still a valid request, so the form of the
     * configurator (which mirrors the same constant) never offers a number the endpoint would refuse.
     */
    public function test_the_highest_allowed_quantity_of_an_item_is_accepted(): void
    {
        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'name' => 'Dveře do obývacího pokoje',
                'quantity' => InquiryItem::MAX_QUANTITY_PER_ITEM,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();

        $this->assertSame(InquiryItem::MAX_QUANTITY_PER_ITEM, $inquiry->items()->sole()->quantity);
        $this->assertSame(InquiryItem::MAX_QUANTITY_PER_ITEM, $inquiry->configuration['summary']['total_doors']);
    }

    public function test_the_request_is_limited_to_the_configured_number_of_items(): void
    {
        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $items = [];

        for ($index = 0; $index <= InquiryItem::MAX_ITEMS_PER_INQUIRY; $index++) {
            $items[] = [
                'quantity' => 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
            ];
        }

        $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'items' => $items,
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasErrors('items');

        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
    }

    public function test_an_unavailable_variant_inside_an_item_is_reported_on_the_item(): void
    {
        [$doorModel] = $this->createCompatibleCatalogSelection();

        $otherModel = DoorModel::query()->create([
            'name' => 'Other test model',
            'slug' => 'other-test-model-'.uniqid(),
            'base_price_without_vat' => 2000,
            'base_price_with_vat' => 2420,
            'active' => true,
        ]);

        $otherVariant = DoorVariant::query()->create([
            'door_model_id' => $otherModel->id,
            'code' => 'OTHER',
            'width' => 800,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);

        $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'quantity' => 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $otherVariant->id,
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasErrors('items.0.door_variant_id');

        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
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
     * @return array{0: DoorModel, 1: DoorVariant, 2: Decor, 3: GlassType, 4: Surcharge}
     */
    private function createCompatibleCatalogSelection(): array
    {
        $doorModel = DoorModel::query()->create([
            'name' => 'Test model '.uniqid(),
            'slug' => 'test-model-'.uniqid(),
            'category' => 'interierove',
            'description' => 'Test model description',
            'base_price_without_vat' => 1000,
            'base_price_with_vat' => 1210,
            'active' => true,
        ]);

        $variant = DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'VAR-'.uniqid(),
            'width' => 800,
            'height' => 2100,
            'opening_direction' => 'leve',
            'opening_type' => 'otocne',
            'has_glass' => true,
            'sliding_possible' => true,
            'price_modifier' => 200,
        ]);

        $decor = Decor::query()->create([
            'name' => 'Test decor '.uniqid(),
            'code' => 'DEC-'.uniqid(),
            'price_modifier' => 100,
            'active' => true,
        ]);

        $glassType = GlassType::query()->create([
            'name' => 'Test glass '.uniqid(),
            'code' => 'GLS-'.uniqid(),
            'opacity' => 70,
            'price_modifier' => 200,
            'active' => true,
        ]);

        $surcharge = Surcharge::query()->create([
            'name' => 'Test surcharge '.uniqid(),
            'code' => 'SUR-'.uniqid(),
            'price_without_vat' => 50,
            'active' => true,
        ]);

        $doorModel->decors()->attach($decor);
        $doorModel->glasses()->attach($glassType);
        $doorModel->surcharges()->attach($surcharge);

        return [$doorModel, $variant, $decor, $glassType, $surcharge];
    }
}
