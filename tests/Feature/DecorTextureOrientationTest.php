<?php

namespace Tests\Feature;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The renderer draws the grain of the decor downwards on the door leaf and on the side jambs of the frame and
 * across on the head piece, and it reaches both runs by turning the very same square tile by a quarter turn.
 * Which run is drawn straight therefore depends on the direction of the uploaded source, and that direction
 * cannot be read from the image: every upload is a square-ish close-up.
 *
 * The direction is published with the decor as data instead - no source is listed as a horizontal one, so every
 * decor is published as running downwards - so the renderer never guesses and the browser and the composed PNG
 * always agree.
 */
class DecorTextureOrientationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_payload_publishes_the_downwards_run_of_an_uploaded_source(): void
    {
        Storage::fake('public');

        $doorModel = $this->createDoorModel();
        $this->createVariant($doorModel);
        $decor = $this->createDecor($doorModel, 'DEC-'.uniqid());
        $this->attachTexture($decor, 'dub-grand.jpg');

        $this->assertSame('vertical', $this->decorPayload($doorModel, $decor->id)['texture_orientation']);
    }

    public function test_the_former_exceptional_texture_is_published_as_downwards_too(): void
    {
        Storage::fake('public');

        $doorModel = $this->createDoorModel();
        $this->createVariant($doorModel);
        $decor = $this->createDecor($doorModel, 'DEC-'.uniqid());
        $this->attachTexture($decor, 'dub-toffi.jpg');

        $this->assertSame('vertical', $this->decorPayload($doorModel, $decor->id)['texture_orientation']);
    }

    public function test_the_former_exceptional_texture_is_published_as_downwards_by_its_decor_code_as_well(): void
    {
        Storage::fake('public');

        $doorModel = $this->createDoorModel();
        $this->createVariant($doorModel);

        // A re-upload replaces the name of the image, so the decor code is read as an identifier too - and it is
        // the code that used to make this decor the single exception.
        $decor = $this->createDecor($doorModel, 'dub-toffi');
        $this->attachTexture($decor, 'IMG-'.uniqid().'.jpg');

        $this->assertSame('vertical', $this->decorPayload($doorModel, $decor->id)['texture_orientation']);
    }

    public function test_a_decor_without_a_texture_keeps_the_downwards_run(): void
    {
        $doorModel = $this->createDoorModel();
        $this->createVariant($doorModel);
        $decor = $this->createDecor($doorModel, 'DEC-'.uniqid());

        $this->assertSame('vertical', $this->decorPayload($doorModel, $decor->id)['texture_orientation']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decorPayload(DoorModel $doorModel, int $decorId): array
    {
        $doorModels = $this->get(route('front.configurator'))
            ->assertOk()
            ->inertiaProps('doorModels');

        $this->assertIsArray($doorModels);

        $payload = collect($doorModels)->firstWhere('id', $doorModel->id);

        $this->assertIsArray($payload, "Model [{$doorModel->id}] is missing from the configurator payload.");

        $decor = collect($payload['decors'])->firstWhere('id', $decorId);

        $this->assertIsArray($decor, "Decor [{$decorId}] is missing from the configurator payload.");

        return $decor;
    }

    private function attachTexture(Decor $decor, string $fileName): void
    {
        $decor
            ->addMediaFromString('decor texture placeholder')
            ->usingFileName($fileName)
            ->toMediaCollection('texture_image');
    }

    private function createDoorModel(): DoorModel
    {
        return DoorModel::query()->create([
            'name' => 'Decor orientation model '.uniqid(),
            'slug' => 'decor-orientation-model-'.uniqid(),
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
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);
    }

    private function createDecor(DoorModel $doorModel, string $code): Decor
    {
        $decor = Decor::query()->create([
            'name' => 'Decor '.uniqid(),
            'code' => $code,
            'price_modifier' => 0,
            'active' => true,
        ]);

        $doorModel->decors()->attach($decor);

        return $decor;
    }
}
