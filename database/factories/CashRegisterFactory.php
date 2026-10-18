<?php

namespace Database\Factories;

use App\Models\CashRegister;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegister>
 */
class CashRegisterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opened_by' => User::factory(),
            'status' => 'open',
            'opening_balance' => fake()->randomFloat(2, 100, 2000),
            'opened_at' => now(),
            'notes' => null,
        ];
    }

    /**
     * Estado de una caja ya cerrada con su cuadre calculado.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'closed',
            'closed_by' => $attributes['opened_by'] ?? User::factory(),
            'closing_balance' => $attributes['opening_balance'] ?? 0,
            'expected_balance' => $attributes['opening_balance'] ?? 0,
            'difference' => 0,
            'closed_at' => now(),
        ]);
    }
}
