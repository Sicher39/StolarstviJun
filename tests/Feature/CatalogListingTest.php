<?php

namespace Tests\Feature;

use App\Models\Decor;
use App\Models\DoorModel;
use App\Models\DoorVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Tests\TestCase;

/**
 * The public catalog is a listing of the published offer: the active door models with their variants
 * and the decors, the glass, the frames and the hardware those models carry.
 *
 * An option is listed once for all the models that offer it, so the visitor reads one card per decor
 * instead of the same picture repeated for every door.
 *
 * The tests run against the catalog that is already in the database, so every assertion looks up the
 * rows it created itself instead of expecting a whole payload of its own.
 */
class CatalogListingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // The test renders the Inertia root view, so Vite must not require a production build manifest.
        $hotFile = storage_path('framework/testing/vite-hot');
        File::ensureDirectoryExists(dirname($hotFile));
        File::put($hotFile, 'http://127.0.0.1:5173');

        Vite::useHotFile($hotFile);
    }

    public function test_the_listing_prints_the_active_models_with_their_variants(): void
    {
        $doorModel = $this->createDoorModel('Katalogový model A');
        $this->createVariant($doorModel, 'LEV');
        $this->createVariant($doorModel, 'PRAV');

        // A retired model is not offered, so neither it nor its variants reach the page.
        $retiredModel = $this->createDoorModel('Katalogový model B', active: false);
        $this->createVariant($retiredModel, 'STARY');

        $models = $this->listing()['models'];

        $payload = $this->modelCard($models, $doorModel);

        $this->assertSame(['LEV', 'PRAV'], array_column($payload['variants'], 'name'));
        $this->assertNull($this->findById($models, $retiredModel->id));
    }

    public function test_an_option_offered_for_two_models_is_listed_once_with_both_of_them(): void
    {
        $firstModel = $this->createDoorModel('Katalogový model A');
        $secondModel = $this->createDoorModel('Katalogový model B');

        $sharedDecor = $this->createDecor('Sdílený dekor');
        $firstModel->decors()->attach($sharedDecor);
        $secondModel->decors()->attach($sharedDecor);

        $cards = $this->cardsOf($this->listing()['decors'], $sharedDecor);

        $this->assertCount(1, $cards);
        $this->assertSame(
            [$firstModel->id, $secondModel->id],
            $cards[0]['model_ids'],
        );
        $this->assertSame(
            ['Katalogový model A', 'Katalogový model B'],
            $cards[0]['model_names'],
        );
    }

    public function test_the_listing_leaves_out_inactive_options_and_options_of_a_retired_model(): void
    {
        $doorModel = $this->createDoorModel('Katalogový model A');
        $offeredDecor = $this->createDecor('Nabízený dekor');
        $inactiveDecor = $this->createDecor('Vypnutý dekor', active: false);
        $doorModel->decors()->attach([$offeredDecor->id, $inactiveDecor->id]);

        $retiredModel = $this->createDoorModel('Katalogový model B', active: false);
        $retiredDecor = $this->createDecor('Dekor vyřazeného modelu');
        $retiredModel->decors()->attach($retiredDecor);

        $decors = $this->listing()['decors'];

        $this->assertCount(1, $this->cardsOf($decors, $offeredDecor));
        $this->assertSame([], $this->cardsOf($decors, $inactiveDecor));
        $this->assertSame([], $this->cardsOf($decors, $retiredDecor));
    }

    /**
     * @return array<string, mixed>
     */
    private function listing(): array
    {
        $listing = $this->get(route('front.catalog'))
            ->assertOk()
            ->inertiaProps('listing');

        $this->assertIsArray($listing);

        return $listing;
    }

    /**
     * @param  array<int, mixed>  $cards
     * @return list<array<string, mixed>>
     */
    private function cardsOf(array $cards, Decor $decor): array
    {
        return array_values(array_filter(
            $cards,
            fn (mixed $card): bool => is_array($card) && ($card['id'] ?? null) === $decor->id,
        ));
    }

    /**
     * @param  array<int, mixed>  $models
     * @return array<string, mixed>
     */
    private function modelCard(array $models, DoorModel $doorModel): array
    {
        $payload = $this->findById($models, $doorModel->id);

        $this->assertIsArray($payload, "Model [{$doorModel->id}] is missing from the catalog payload.");

        return $payload;
    }

    /**
     * @param  array<int, mixed>  $items
     */
    private function findById(array $items, int $id): ?array
    {
        foreach ($items as $item) {
            if (is_array($item) && ($item['id'] ?? null) === $id) {
                return $item;
            }
        }

        return null;
    }

    private function createDoorModel(string $name, bool $active = true): DoorModel
    {
        return DoorModel::query()->create([
            'name' => $name,
            'slug' => 'katalog-'.uniqid(),
            'base_price_without_vat' => 1000,
            'base_price_with_vat' => 1210,
            'active' => $active,
        ]);
    }

    private function createVariant(DoorModel $doorModel, string $code): DoorVariant
    {
        return DoorVariant::query()->create([
            'door_model_id' => $doorModel->id,
            // The code is unique per model, which is exactly the name the catalog card shows.
            'code' => $code,
            'width' => 800,
            'height' => 2100,
            'has_glass' => false,
            'sliding_possible' => false,
            'price_modifier' => 0,
        ]);
    }

    private function createDecor(string $name, bool $active = true): Decor
    {
        return Decor::query()->create([
            'name' => $name.' '.uniqid(),
            'code' => 'DEC-'.uniqid(),
            'price_modifier' => 0,
            'active' => $active,
        ]);
    }
}
