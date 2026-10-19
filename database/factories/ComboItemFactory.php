<?php

namespace Database\Factories;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComboItem>
 */
class ComboItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $combo = Combo::factory()->create();
        $product = Product::factory()->create(['tenant_id' => $combo->tenant_id]);

        return [
            'tenant_id' => $combo->tenant_id,
            'combo_id' => $combo->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 1,
            'eligible_variant_ids' => null,
        ];
    }
}
