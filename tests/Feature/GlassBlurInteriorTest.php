<?php

namespace Tests\Feature;

use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use App\Models\Inquiry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The room behind a glass is drawn either sharp or blurred. The rule used to be hard coded in the browser, with the
 * clear glass as the only exception; the catalog now carries it per glass, so an administrator can blur or sharpen
 * any glass. The inquiry snapshots the flag together with the selected glass, which keeps a sent request readable
 * exactly as it was quoted even after the catalog changes.
 */
class GlassBlurInteriorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_configurator_payload_publishes_the_blur_flag_of_every_glass(): void
    {
        $doorModel = $this->createDoorModel();
        $this->createVariant($doorModel);

        // A glass created without the flag behaves the way every glass did before the flag existed.
        $blurredGlass = $this->createGlass($doorModel, 'Satinato');
        $sharpGlass = $this->createGlass($doorModel, 'Čiré', blurInterior: false);

        $this->assertTrue($blurredGlass->fresh()->blur_interior);

        $glasses = $this->configuratorModelPayload($doorModel)['glasses'];

        // The flag controls the preview of any glass, not only of the clear one.
        $this->assertTrue($this->glassPayload($glasses, $blurredGlass->id)['blur_interior']);
        $this->assertFalse($this->glassPayload($glasses, $sharpGlass->id)['blur_interior']);
    }

    public function test_an_inquiry_snapshots_the_blur_flag_of_the_selected_glass(): void
    {
        $doorModel = $this->createDoorModel();
        $variant = $this->createVariant($doorModel);
        $glass = $this->createGlass($doorModel, 'Čiré', blurInterior: false);

        $email = 'blur-test-'.uniqid().'@example.com';

        $this->post(route('front.configurator.inquiry.store'), [
            'door_model_id' => $doorModel->id,
            'door_variant_id' => $variant->id,
            'glass_type_id' => $glass->id,
            'customer_name' => 'Jan Novak',
            'customer_email' => $email,
            'gdpr_consent' => '1',
        ])
            ->assertRedirect(route('front.configurator'))
            ->assertSessionHasNoErrors();

        $item = Inquiry::query()
            ->where('customer_email', $email)
            ->firstOrFail()
            ->items()
            ->firstOrFail();

        $this->assertFalse($item->snapshot()['glass_type']['blur_interior']);

        // The snapshot is historical: editing the catalog later must not redraw what the customer sent.
        $glass->update(['blur_interior' => true]);

        $this->assertFalse($item->fresh()->snapshot()['glass_type']['blur_interior']);
    }

    /**
     * @return array<string, mixed>
     */
    private function configuratorModelPayload(DoorModel $doorModel): array
    {
        $doorModels = $this->get(route('front.configurator'))
            ->assertOk()
            ->inertiaProps('doorModels');

        $this->assertIsArray($doorModels);

        $payload = collect($doorModels)->firstWhere('id', $doorModel->id);

        $this->assertIsArray($payload, "Model [{$doorModel->id}] is missing from the configurator payload.");

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $glasses
     * @return array<string, mixed>
     */
    private function glassPayload(array $glasses, int $glassId): array
    {
        $glass = collect($glasses)->firstWhere('id', $glassId);

        $this->assertIsArray($glass, "Glass [{$glassId}] is missing from the configurator payload.");

        return $glass;
    }

    private function createDoorModel(): DoorModel
    {
        return DoorModel::query()->create([
            'name' => 'Blur test model '.uniqid(),
            'slug' => 'blur-test-model-'.uniqid(),
            'base_price_without_vat' => 1000,
            'active' => true,
        ]);
    }

    private function createVariant(DoorModel $doorModel): DoorVariant
    {
        return DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'VAR-'.uniqid(),
            'width' => 800,
            'height' => 2100,
            'has_glass' => true,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);
    }

    /**
     * A glass without an explicit flag is left to the database default, which is the behaviour of the catalog
     * before the flag existed.
     */
    private function createGlass(DoorModel $doorModel, string $name, ?bool $blurInterior = null): GlassType
    {
        $attributes = [
            'name' => $name.' '.uniqid(),
            'opacity' => 70,
            'price_modifier' => 0,
            'active' => true,
        ];

        if ($blurInterior !== null) {
            $attributes['blur_interior'] = $blurInterior;
        }

        $glass = GlassType::query()->create($attributes);

        $doorModel->glasses()->attach($glass);

        return $glass;
    }
}
