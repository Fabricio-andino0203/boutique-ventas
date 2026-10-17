<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlanSeeder extends Seeder
{
    /**
     * Planes de ejemplo. Precios en HNL y funcionalidades por plan.
     * El propietario puede editarlos luego desde el backoffice.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Básico',
                'description' => 'Ideal para empezar: ventas, inventario y reportes esenciales.',
                'price' => 450,
                'duration_days' => 30,
                'sort_order' => 1,
                'limits' => ['max_products' => 100, 'max_variants' => 300, 'max_users' => 1],
                'features' => ['pos', 'inventory', 'variants', 'customers', 'cash_register', 'reports'],
            ],
            [
                'name' => 'Pro',
                'description' => 'Para boutiques en crecimiento: suma compras, proveedores y devoluciones.',
                'price' => 850,
                'duration_days' => 30,
                'sort_order' => 2,
                'limits' => ['max_products' => 500, 'max_variants' => 2000, 'max_users' => 3],
                'features' => ['pos', 'inventory', 'variants', 'customers', 'suppliers', 'purchases', 'cash_register', 'returns', 'expenses', 'reports'],
            ],
            [
                'name' => 'Premium',
                'description' => 'Todas las funciones, sin límites y con múltiples usuarios.',
                'price' => 1500,
                'duration_days' => 30,
                'sort_order' => 3,
                'limits' => ['max_products' => null, 'max_variants' => null, 'max_users' => 10],
                'features' => ['pos', 'inventory', 'variants', 'customers', 'suppliers', 'purchases', 'cash_register', 'returns', 'expenses', 'reports', 'multi_user'],
            ],
        ];

        foreach ($plans as $data) {
            $featureKeys = $data['features'];
            unset($data['features']);

            $plan = Plan::updateOrCreate(
                ['slug' => Str::slug($data['name'])],
                [...$data, 'currency' => 'HNL', 'is_active' => true, 'is_public' => true],
            );

            $featureIds = Feature::whereIn('key', $featureKeys)->pluck('id');
            $plan->features()->sync($featureIds);
        }
    }
}
