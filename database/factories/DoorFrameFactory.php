<?php

namespace Database\Factories;

use App\Models\DoorFrame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoorFrame>
 */
class DoorFrameFactory extends Factory
{
    protected $model = DoorFrame::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zárubeň '.fake()->unique()->word(),
            // The code is left to the model, so a factory row gets the same system code the
            // administration gets from the catalog form.
            'price_without_vat' => fake()->randomFloat(2, 0, 5000),
            'sort_order' => 0,
            'active' => true,
            // A picture is optional, but a row that has none still publishes "show it": the switch
            // only hides a picture that exists.
            'show_image' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
        ]);
    }

    public function imageHidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'show_image' => false,
        ]);
    }

    public function pricedAt(float $priceWithoutVat): static
    {
        return $this->state(fn (array $attributes): array => [
            'price_without_vat' => $priceWithoutVat,
        ]);
    }

    public function withSortOrder(int $sortOrder): static
    {
        return $this->state(fn (array $attributes): array => [
            'sort_order' => $sortOrder,
        ]);
    }
}
