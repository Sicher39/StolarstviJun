<?php

namespace Tests\Feature;

use App\Models\DoorModel;
use App\Models\DoorVariant;
use App\Models\GlassType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The glass step of the configurator is a permanent part of the step menu, even before a variant is chosen, and it
 * explains itself from the payload. The payload therefore has to tell „this variant is not glazed“ apart from „this
 * model publishes no glass at all“: the step reads `has_glass` of the chosen variant and the active glasses of the
 * model, and never asks the catalog on its own.
 */
class ConfiguratorGlassAvailabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payload_publishes_active_glasses_of_a_glass_capable_model(): void
    {
        $doorModel = $this->createDoorModel();
        $glassableVariant = $this->createVariant($doorModel, hasGlass: true);
        $activeGlass = $this->createGlass($doorModel, 'Clear glass', active: true);
        $this->createGlass($doorModel, 'Retired glass', active: false);

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertTrue($this->variantPayload($payload, $glassableVariant->id)['has_glass']);

        // A retired glass type must never reach the step, because the visitor would be offered a door the catalog
        // no longer quotes.
        $this->assertSame([$activeGlass->id], array_column($payload['glasses'], 'id'));
    }

    public function test_payload_publishes_an_empty_glass_list_for_a_variant_without_glazing(): void
    {
        $doorModel = $this->createDoorModel();
        $solidVariant = $this->createVariant($doorModel, hasGlass: false);

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertFalse($this->variantPayload($payload, $solidVariant->id)['has_glass']);

        // The step stays in the menu and explains the missing choice, so the list is published but empty.
        $this->assertSame([], $payload['glasses']);
    }

    public function test_payload_keeps_glasses_of_another_model_out(): void
    {
        $doorModel = $this->createDoorModel();
        $this->createVariant($doorModel, hasGlass: true);

        $otherModel = $this->createDoorModel();
        $this->createGlass($otherModel, 'Foreign glass');

        $this->assertSame([], $this->configuratorModelPayload($doorModel)['glasses']);
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function variantPayload(array $payload, int $variantId): array
    {
        $variant = collect($payload['variants'])->firstWhere('id', $variantId);

        $this->assertIsArray($variant, "Variant [{$variantId}] is missing from the configurator payload.");

        return $variant;
    }

    private function createDoorModel(): DoorModel
    {
        return DoorModel::query()->create([
            'name' => 'Glass test model '.uniqid(),
            'slug' => 'glass-test-model-'.uniqid(),
            'base_price_without_vat' => 1000,
            'active' => true,
        ]);
    }

    private function createVariant(DoorModel $doorModel, bool $hasGlass): DoorVariant
    {
        return DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => 'VAR-'.uniqid(),
            'width' => 800,
            'height' => 2100,
            'has_glass' => $hasGlass,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);
    }

    private function createGlass(DoorModel $doorModel, string $name, bool $active = true): GlassType
    {
        $glass = GlassType::query()->create([
            'name' => $name.' '.uniqid(),
            'opacity' => 70,
            'price_modifier' => 0,
            'active' => $active,
        ]);

        $doorModel->glasses()->attach($glass);

        return $glass;
    }
}
