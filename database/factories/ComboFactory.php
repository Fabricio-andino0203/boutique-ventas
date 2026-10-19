<?php

namespace Database\Factories;

use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Combo>
 */
class ComboFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $product = Product::factory()->create(['type' => 'combo', 'tenant_id' => $tenant->id]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'tenant_id' => $tenant->id]);

        return [
            'tenant_id' => $tenant->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => 'Combo '.fake()->unique()->words(2, true),
            'pricing_mode' => 'cheapest',
            'price' => fake()->numberBetween(100, 2000),
            'base_price' => null,
            'stock_mode' => 'derived',
            'stock_quantity' => 0,
            'min_stock' => 0,
            'is_active' => true,
        ];
    }
}
