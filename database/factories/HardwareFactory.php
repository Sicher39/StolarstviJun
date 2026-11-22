<?php

namespace Database\Factories;

use App\Models\Hardware;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hardware>
 */
class HardwareFactory extends Factory
{
    protected $model = Hardware::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Kování '.fake()->unique()->word(),
            // The code is left to the model, so a factory row gets the same system code the
            // administration gets from the catalog form.
            'price_without_vat' => fake()->randomFloat(2, 0, 5000),
            'sort_order' => 0,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => false,
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
