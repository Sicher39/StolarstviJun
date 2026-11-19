<?php

namespace Database\Factories;

use App\Models\Reference;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reference>
 */
class ReferenceFactory extends Factory
{
    protected $model = Reference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'heading' => 'Zakázka '.fake()->words(3, true),
            'description' => fake()->city(),
            'category' => fake()->randomElement([
                Reference::CATEGORY_DOORS,
                Reference::CATEGORY_FURNITURE,
                Reference::CATEGORY_KITCHEN,
                Reference::CATEGORY_OTHER,
            ]),
            'status' => Reference::STATUS_DRAFT,
            'placement' => null,
            'images' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Reference::STATUS_PUBLISHED,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Reference::STATUS_DRAFT,
        ]);
    }

    public function category(string $category): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category,
        ]);
    }

    public function placement(?string $placement): static
    {
        return $this->state(fn (array $attributes): array => [
            'placement' => $placement,
        ]);
    }

    /**
     * @param  list<string>  $paths
     */
    public function images(array $paths): static
    {
        return $this->state(fn (array $attributes): array => [
            'images' => $paths,
        ]);
    }
}
