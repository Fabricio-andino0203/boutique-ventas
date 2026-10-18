<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'size' => fake()->randomElement(['S', 'M', 'L', 'XL']),
            'color' => fake()->safeColorName(),
            'price' => fake()->numberBetween(100, 2000),
            'cost' => fake()->numberBetween(50, 1000),
            'stock_quantity' => fake()->numberBetween(0, 50),
            'min_stock' => 0,
            'is_active' => true,
        ];
    }
}
