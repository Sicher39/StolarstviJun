<?php

namespace Tests\Feature;

use App\Models\Decor;
use App\Models\DoorFrame;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Hardware;
use App\Models\Inquiry;
use App\Services\Inquiry\InquirySnapshotPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The „Ostatní“ step of the configurator: the frame and the hardware.
 *
 * Both are accessories of the chosen door model, so the payload only offers what the catalog of that model
 * carries, the endpoint accepts only an active accessory that belongs to the submitted model, and the price of
 * one piece is read from the database instead of being trusted from the browser.
 */
class ConfiguratorAccessorySelectionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_payload_publishes_only_the_active_accessories_of_the_model(): void
    {
        [$doorModel, , , , , $doorFrame, $hardware] = $this->createCompatibleCatalogSelection();
        $otherModel = $this->createDoorModel('Model bez doplňků '.uniqid());

        $inactiveFrame = DoorFrame::factory()->inactive()->create();
        $inactiveFrame->doorModels()->attach($doorModel);

        $inactiveHardware = Hardware::factory()->inactive()->create();
        $inactiveHardware->doorModels()->attach($doorModel);

        $foreignFrame = DoorFrame::factory()->create();
        $foreignFrame->doorModels()->attach($otherModel);

        $doorModels = $this->get(route('front.configurator'))
            ->assertOk()
            ->inertiaProps('doorModels');

        $payload = collect($doorModels)->firstWhere('id', $doorModel->id);

        $this->assertIsArray($payload);

        // Only the active accessory of this model is offered; the inactive row and the row of another model are not.
        $this->assertSame([$doorFrame->id], array_column($payload['frames'], 'id'));
        $this->assertSame([$hardware->id], array_column($payload['hardware'], 'id'));

        $this->assertSame($doorFrame->name, $payload['frames'][0]['name']);
        $this->assertSame((float) $doorFrame->price_without_vat, $payload['frames'][0]['price_without_vat']);
        $this->assertSame((float) $hardware->price_without_vat, $payload['hardware'][0]['price_without_vat']);

        // A model without any accessory publishes two empty lists, so the step can explain itself.
        $otherPayload = collect($doorModels)->firstWhere('id', $otherModel->id);

        $this->assertIsArray($otherPayload);
        $this->assertSame([], $otherPayload['frames']);
        $this->assertSame([], $otherPayload['hardware']);
    }

    public function test_the_payload_publishes_the_picture_of_the_hardware(): void
    {
        Storage::fake('public');

        [$doorModel, , , , , , $hardware] = $this->createCompatibleCatalogSelection();

        $hardware->addMedia(UploadedFile::fake()->create('klika.jpg', 20, 'image/jpeg'))
            ->toMediaCollection('image');

        $doorModels = $this->get(route('front.configurator'))
            ->assertOk()
            ->inertiaProps('doorModels');

        $payload = collect($doorModels)->firstWhere('id', $doorModel->id);

        $this->assertIsArray($payload);
        $this->assertNotNull($payload['hardware'][0]['image_url']);

        // A frame publishes the same key, but this one has no uploaded picture, so there is nothing to show.
        $this->assertArrayHasKey('image_url', $payload['frames'][0]);
        $this->assertNull($payload['frames'][0]['image_url']);
    }

    public function test_the_frame_picture_is_hidden_in_the_configurator_when_the_catalog_turns_it_off(): void
    {
        Storage::fake('public');

        [$doorModel, , , , , $doorFrame] = $this->createCompatibleCatalogSelection();

        $doorFrame->addMedia(UploadedFile::fake()->create('zaruben.jpg', 20, 'image/jpeg'))
            ->toMediaCollection('image');

        // The switch is on for every row by default, so a freshly uploaded picture is published.
        $this->assertTrue($doorFrame->show_image);
        $this->assertNotNull($this->firstFramePayload($doorModel)['image_url']);

        $doorFrame->update(['show_image' => false]);

        $frame = $this->firstFramePayload($doorModel);

        // Only the picture is hidden: the frame stays offered with its name, its code and its price, so the
        // visitor can still choose the installation type and the rest of the card is unchanged.
        $this->assertSame($doorFrame->id, $frame['id']);
        $this->assertSame($doorFrame->name, $frame['name']);
        $this->assertSame($doorFrame->code, $frame['code']);
        $this->assertEquals((float) $doorFrame->price_without_vat, $frame['price_without_vat']);
        $this->assertNull($frame['image_url']);
    }

    public function test_an_item_with_a_frame_and_hardware_is_priced_and_snapshotted_from_the_catalog(): void
    {
        [$doorModel, $variant, $decor, $glassType, , $doorFrame, $hardware] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'name' => 'Dveře do ložnice',
                'quantity' => 2,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
                'decor_id' => $decor->id,
                'glass_type_id' => $glassType->id,
                'door_frame_id' => $doorFrame->id,
                'hardware_id' => $hardware->id,
                'surcharge_ids' => [],
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();
        $item = $inquiry->items()->sole();

        // The references are stored next to the snapshot, so the administration can edit the item with real ids.
        $this->assertSame($doorFrame->id, $item->door_frame_id);
        $this->assertSame($hardware->id, $item->hardware_id);

        // 1000 base + 200 variant + 100 decor + 200 glass + 150 frame + 300 hardware.
        $this->assertEquals(1950.0, (float) $item->unit_price_without_vat);
        $this->assertEquals(3900.0, (float) $item->price_without_vat);
        $this->assertEquals(4719.0, (float) $item->price_with_vat);

        $snapshot = $item->snapshot();

        $this->assertSame($doorFrame->name, $snapshot['door_frame']['name']);
        $this->assertSame((float) $doorFrame->price_without_vat, (float) $snapshot['door_frame']['price_without_vat']);
        $this->assertSame($hardware->name, $snapshot['hardware']['name']);
        $this->assertSame((float) $hardware->price_without_vat, (float) $snapshot['hardware']['price_without_vat']);

        $breakdown = $snapshot['price_breakdown'];

        $this->assertSame(
            ['base_price', 'door_variant', 'decor', 'glass_type', 'door_frame', 'hardware'],
            array_column($breakdown['lines'], 'key'),
        );
        $this->assertSame(
            [1000.0, 200.0, 100.0, 200.0, 150.0, 300.0],
            array_map(fn (array $line): float => (float) $line['amount_without_vat'], $breakdown['lines']),
        );

        // The single door view on the top level keeps describing the first item, accessories included.
        $this->assertSame($doorFrame->name, $inquiry->configuration['door_frame']['name']);
        $this->assertSame($hardware->name, $inquiry->configuration['hardware']['name']);

        // The recap of the request counts the line price, not the price per piece.
        $this->assertEquals(3900.0, (float) $inquiry->price_without_vat);
        $this->assertEquals(4719.0, (float) $inquiry->price_with_vat);
        $this->assertSame(2, $inquiry->configuration['summary']['total_doors']);
    }

    public function test_the_snapshot_of_an_item_with_accessories_reads_as_selection_and_price_lines(): void
    {
        [$doorModel, $variant, $decor, $glassType, , $doorFrame, $hardware] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'quantity' => 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
                'decor_id' => $decor->id,
                'glass_type_id' => $glassType->id,
                'door_frame_id' => $doorFrame->id,
                'hardware_id' => $hardware->id,
                'surcharge_ids' => [],
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasNoErrors();

        $item = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail()->items()->sole();
        $presenter = app(InquirySnapshotPresenter::class);

        $selection = collect($presenter->itemLines($item->snapshot()))->pluck('value', 'label');

        $this->assertSame($doorFrame->name.' ('.$doorFrame->code.')', $selection['Zárubeň']);
        $this->assertSame($hardware->name.' ('.$hardware->code.')', $selection['Kování']);

        $prices = collect($presenter->priceBreakdownLines($item->snapshot()))->pluck('value', 'label');

        $this->assertTrue($prices->has('Zárubeň '.$doorFrame->name.' ('.$doorFrame->code.') bez DPH'));
        $this->assertTrue($prices->has('Kování '.$hardware->name.' ('.$hardware->code.') bez DPH'));
    }

    public function test_an_inquiry_without_accessories_keeps_them_empty(): void
    {
        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'quantity' => 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
                'surcharge_ids' => [],
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail();
        $item = $inquiry->items()->sole();

        $this->assertNull($item->door_frame_id);
        $this->assertNull($item->hardware_id);
        $this->assertNull($item->snapshot()['door_frame']);
        $this->assertNull($item->snapshot()['hardware']);
        $this->assertNull($inquiry->configuration['door_frame']);

        // A snapshot without the accessory blocks adds no line for them, so an older request reads as it was sent.
        $selection = collect(app(InquirySnapshotPresenter::class)->itemLines($item->snapshot()))->pluck('label');

        $this->assertFalse($selection->contains('Zárubeň'));
        $this->assertFalse($selection->contains('Kování'));
    }

    public function test_a_frame_of_another_model_is_refused(): void
    {
        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();
        $otherModel = $this->createDoorModel('Jiný model '.uniqid());

        $foreignFrame = DoorFrame::factory()->create();
        $foreignFrame->doorModels()->attach($otherModel);

        $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'quantity' => 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
                'door_frame_id' => $foreignFrame->id,
                'surcharge_ids' => [],
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasErrors('items.0.door_frame_id');

        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
    }

    public function test_hardware_of_another_model_is_refused(): void
    {
        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();
        $otherModel = $this->createDoorModel('Jiný model '.uniqid());

        $foreignHardware = Hardware::factory()->create();
        $foreignHardware->doorModels()->attach($otherModel);

        $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'quantity' => 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
                'hardware_id' => $foreignHardware->id,
                'surcharge_ids' => [],
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasErrors('items.0.hardware_id');

        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
    }

    public function test_an_inactive_accessory_of_the_model_is_refused(): void
    {
        [$doorModel, $variant] = $this->createCompatibleCatalogSelection();

        $inactiveFrame = DoorFrame::factory()->inactive()->create();
        $inactiveFrame->doorModels()->attach($doorModel);

        $this->from(route('front.configurator'))->post(route('front.configurator.inquiry.store'), [
            'items' => [[
                'quantity' => 1,
                'door_model_id' => $doorModel->id,
                'door_variant_id' => $variant->id,
                'door_frame_id' => $inactiveFrame->id,
                'surcharge_ids' => [],
            ]],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasErrors('items.0.door_frame_id');

        $this->assertDatabaseMissing(Inquiry::class, ['customer_email' => 'jan@example.com']);
    }

    /**
     * The one piece payload of the older flow carries the accessory fields on the top level, so a browser that
     * was cached before the items existed is still priced with them.
     */
    public function test_the_flat_payload_accepts_the_accessories_of_the_step(): void
    {
        [$doorModel, $variant, , , , $doorFrame, $hardware] = $this->createCompatibleCatalogSelection();

        $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'door_frame_id' => $doorFrame->id,
            'hardware_id' => $hardware->id,
            'surcharge_ids' => [],
            'customer_name' => 'Jan Novak',
            'customer_email' => 'jan@example.com',
            'gdpr_consent' => '1',
        ])->assertSessionHasNoErrors();

        $item = Inquiry::query()->where('customer_email', 'jan@example.com')->firstOrFail()->items()->sole();

        $this->assertSame($doorFrame->id, $item->door_frame_id);
        $this->assertSame($hardware->id, $item->hardware_id);
        // 1000 base + 200 variant + 150 frame + 300 hardware.
        $this->assertEquals(1650.0, (float) $item->unit_price_without_vat);
    }

    private function createDoorModel(string $name): DoorModel
    {
        return DoorModel::query()->create([
            'name' => $name,
            'slug' => 'accessory-selection-'.uniqid(),
            'base_price_without_vat' => 1000,
            'active' => true,
        ]);
    }

    /**
     * The first frame the payload of the model offers, read through the public page exactly as the browser
     * receives it.
     *
     * @return array<string, mixed>
     */
    private function firstFramePayload(DoorModel $doorModel): array
    {
        $doorModels = $this->get(route('front.configurator'))
            ->assertOk()
            ->inertiaProps('doorModels');

        $payload = collect($doorModels)->firstWhere('id', $doorModel->id);

        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload['frames']);

        return $payload['frames'][0];
    }

    /**
     * @return array{0: DoorModel, 1: DoorVariant, 2: Decor, 3: GlassType, 4: null, 5: DoorFrame, 6: Hardware}
     */
    private function createCompatibleCatalogSelection(): array
    {
        $doorModel = $this->createDoorModel('Test model '.uniqid());

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

        $doorFrame = DoorFrame::factory()->pricedAt(150)->create();
        $doorFrame->doorModels()->attach($doorModel);

        $hardware = Hardware::factory()->pricedAt(300)->create();
        $hardware->doorModels()->attach($doorModel);

        $doorModel->decors()->attach($decor);
        $doorModel->glasses()->attach($glassType);

        return [$doorModel, $variant, $decor, $glassType, null, $doorFrame, $hardware];
    }
}
