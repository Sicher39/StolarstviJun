<?php

namespace Tests\Feature;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConfiguratorDefaultsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_payload_prefers_the_configured_default_variant_and_decor(): void
    {
        $doorModel = $this->createDoorModel();
        $firstVariant = $this->createVariant($doorModel, 'FIRST');
        $defaultVariant = $this->createVariant($doorModel, 'DEFAULT');
        $firstDecor = $this->createDecor($doorModel, 'Zebra decor');
        $defaultDecor = $this->createDecor($doorModel, 'Alpha decor');

        $doorModel->update([
            'default_variant_id' => $defaultVariant->id,
            'default_decor_id' => $defaultDecor->id,
        ]);

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertSame($defaultVariant->id, $payload['initial_variant_id']);
        $this->assertSame($defaultDecor->id, $payload['initial_decor_id']);

        // Option lists stay deterministic (id ascending), so the configured defaults need not be the first cards.
        $this->assertSame($firstVariant->id, $payload['variants'][0]['id']);
        $this->assertSame($firstDecor->id, $payload['decors'][0]['id']);
    }

    public function test_payload_falls_back_to_first_inserted_variant_and_first_available_decor(): void
    {
        $doorModel = $this->createDoorModel();
        $firstVariant = $this->createVariant($doorModel, 'FIRST');
        $this->createVariant($doorModel, 'SECOND');
        $firstDecor = $this->createDecor($doorModel, 'Zebra decor');
        $this->createDecor($doorModel, 'Alpha decor');

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertSame($firstVariant->id, $payload['initial_variant_id']);
        $this->assertSame($firstDecor->id, $payload['initial_decor_id']);
    }

    public function test_payload_ignores_default_variant_of_another_model(): void
    {
        $doorModel = $this->createDoorModel();
        $firstVariant = $this->createVariant($doorModel, 'FIRST');

        $otherModel = $this->createDoorModel();
        $otherVariant = $this->createVariant($otherModel, 'FOREIGN');

        $doorModel->update(['default_variant_id' => $otherVariant->id]);

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertSame($firstVariant->id, $payload['initial_variant_id']);
    }

    public function test_payload_ignores_inactive_default_decor(): void
    {
        $doorModel = $this->createDoorModel();
        $availableDecor = $this->createDecor($doorModel, 'Zebra decor');
        $inactiveDecor = $this->createDecor($doorModel, 'Inactive decor', active: false);

        $doorModel->update(['default_decor_id' => $inactiveDecor->id]);

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertSame($availableDecor->id, $payload['initial_decor_id']);
        $this->assertSame([$availableDecor->id], array_column($payload['decors'], 'id'));
    }

    public function test_payload_ignores_default_decor_of_another_model(): void
    {
        $doorModel = $this->createDoorModel();
        $availableDecor = $this->createDecor($doorModel, 'Zebra decor');

        $otherModel = $this->createDoorModel();
        $foreignDecor = $this->createDecor($otherModel, 'Foreign decor');

        $doorModel->update(['default_decor_id' => $foreignDecor->id]);

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertSame($availableDecor->id, $payload['initial_decor_id']);
    }

    public function test_payload_returns_null_defaults_when_model_has_no_options(): void
    {
        $doorModel = $this->createDoorModel();

        $payload = $this->configuratorModelPayload($doorModel);

        $this->assertNull($payload['initial_variant_id']);
        $this->assertNull($payload['initial_decor_id']);
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

    private function createDoorModel(): DoorModel
    {
        return DoorModel::query()->create([
            'name' => 'Default test model '.uniqid(),
            'slug' => 'default-test-model-'.uniqid(),
            'base_price_without_vat' => 1000,
            'base_price_with_vat' => 1210,
            'active' => true,
        ]);
    }

    private function createVariant(DoorModel $doorModel, string $code): DoorVariant
    {
        return DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            'code' => $code.' '.uniqid(),
            'width' => 800,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);
    }

    private function createDecor(DoorModel $doorModel, string $name, bool $active = true): Decor
    {
        $decor = Decor::query()->create([
            'name' => $name.' '.uniqid(),
            'code' => 'DEC-'.uniqid(),
            'price_modifier' => 0,
            'active' => $active,
        ]);

        $doorModel->decors()->attach($decor);

        return $decor;
    }
}
