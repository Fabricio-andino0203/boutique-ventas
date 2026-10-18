<?php

namespace Database\Factories;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => null,
            'status' => 'received',
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'purchased_at' => now(),
            'received_at' => now(),
        ];
    }
}
