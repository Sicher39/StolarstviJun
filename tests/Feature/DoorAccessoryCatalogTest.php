<?php

namespace Tests\Feature;

use App\Enums\DoorModelType;
use App\Models\DoorFrame;
use App\Models\DoorModel;
use App\Models\Hardware;
use App\Services\VatCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The catalog foundation of the „Ostatní“ section of the configurator.
 *
 * The frame and the hardware are accessories: they are priced on their own and they decide, from
 * their side, which door models may be configured with them. The type of a model is what the
 * configurator orders the models by, so both the cast and the published order are checked here.
 */
class DoorAccessoryCatalogTest extends TestCase
{
    use DatabaseTransactions;

    public function test_configurator_lists_foiled_models_before_veneered_ones_and_untyped_last(): void
    {
        $veneered = $this->createDoorModel('Aaa dýhovaný model', DoorModelType::Veneered);
        $foiled = $this->createDoorModel('Zzz foliový model', DoorModelType::Foiled);
        $untyped = $this->createDoorModel('Mmm nezařazený model');

        $doorModels = $this->get(route('front.configurator'))
            ->assertOk()
            ->inertiaProps('doorModels');

        $this->assertIsArray($doorModels);

        $orderedIds = array_column(
            array_values(array_filter(
                $doorModels,
                fn (array $model): bool => in_array($model['id'], [$veneered->id, $foiled->id, $untyped->id], true),
            )),
            'id',
        );

        // The names deliberately sort the other way round, so only the type can produce this order.
        $this->assertSame([$foiled->id, $veneered->id, $untyped->id], $orderedIds);
    }

    public function test_payload_publishes_the_type_of_a_classified_model(): void
    {
        $doorModel = $this->createDoorModel('Dýhovaný model '.uniqid(), DoorModelType::Veneered);

        $doorModels = $this->get(route('front.configurator'))
            ->assertOk()
            ->inertiaProps('doorModels');

        $payload = collect($doorModels)->firstWhere('id', $doorModel->id);

        $this->assertIsArray($payload);
        $this->assertSame('veneered', $payload['type']);
    }

    public function test_the_type_is_cast_to_the_enum_and_stays_null_when_it_was_never_set(): void
    {
        $typed = $this->createDoorModel('Foliový model '.uniqid(), DoorModelType::Foiled);
        $untyped = $this->createDoorModel('Nezařazený model '.uniqid());

        $this->assertSame(DoorModelType::Foiled, $typed->fresh()->type);
        $this->assertNull($untyped->fresh()->type);
    }

    public function test_a_frame_and_hardware_are_offered_per_model_from_the_accessory_side(): void
    {
        $doorModel = $this->createDoorModel('Model se zárubní '.uniqid(), DoorModelType::Foiled);
        $otherModel = $this->createDoorModel('Model bez zárubně '.uniqid(), DoorModelType::Foiled);

        $frame = DoorFrame::factory()->create(['name' => 'Bezfalcová '.uniqid()]);
        $frame->doorModels()->attach($doorModel);

        $hardware = Hardware::factory()->create(['name' => 'Kování '.uniqid()]);
        $hardware->doorModels()->attach($doorModel);

        // The system code is what the snapshot uses as the stable key, so it has to be generated.
        $this->assertStringStartsWith('FRM-', (string) $frame->code);
        $this->assertStringStartsWith('HWD-', (string) $hardware->code);

        $this->assertSame([$frame->id], $this->idsOf($doorModel->frames()->pluck('door_frames.id')->all()));
        $this->assertSame([$hardware->id], $this->idsOf($doorModel->hardware()->pluck('hardware.id')->all()));

        // A model the accessory was not attached to must never receive it.
        $this->assertSame(0, $otherModel->frames()->count());
        $this->assertSame(0, $otherModel->hardware()->count());
    }

    public function test_the_gross_price_of_an_accessory_is_derived_from_the_central_vat_rate(): void
    {
        $frame = DoorFrame::factory()->pricedAt(100)->create();
        $hardware = Hardware::factory()->pricedAt(250)->create();

        $this->assertEqualsWithDelta(VatCalculator::applyRate(100), (float) $frame->price_with_vat, 0.001);
        $this->assertEqualsWithDelta(VatCalculator::applyRate(250), (float) $hardware->fresh()->price_with_vat, 0.001);

        // The gross price is never stored, so the row itself carries the net price only.
        $this->assertArrayNotHasKey('price_with_vat', $hardware->getAttributes());
    }

    private function createDoorModel(string $name, ?DoorModelType $type = null): DoorModel
    {
        return DoorModel::query()->create([
            'name' => $name,
            'slug' => 'accessory-test-'.uniqid(),
            'type' => $type?->value,
            'base_price_without_vat' => 1000,
            'active' => true,
        ]);
    }

    /**
     * Database drivers differ in whether a plucked foreign key comes back as an int or as a string,
     * so the values are normalised before they are compared with model keys.
     *
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function idsOf(array $ids): array
    {
        return array_values(array_map(fn (mixed $id): int => (int) $id, $ids));
    }
}
