<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'brand' => fake()->optional()->company(),
            'default_price' => fake()->numberBetween(100, 2000),
            'default_cost' => fake()->numberBetween(50, 1000),
            'is_active' => true,
        ];
    }
}
