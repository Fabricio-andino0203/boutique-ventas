<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Catálogo maestro de funcionalidades asignables a los planes.
     */
    public function run(): void
    {
        $features = [
            ['key' => 'pos', 'name' => 'Punto de venta (POS)'],
            ['key' => 'inventory', 'name' => 'Control de inventario'],
            ['key' => 'variants', 'name' => 'Variantes (tallas, colores, modelos)'],
            ['key' => 'customers', 'name' => 'Gestión de clientes'],
            ['key' => 'suppliers', 'name' => 'Gestión de proveedores'],
            ['key' => 'purchases', 'name' => 'Compras'],
            ['key' => 'cash_register', 'name' => 'Caja (apertura y cierre)'],
            ['key' => 'returns', 'name' => 'Devoluciones'],
            ['key' => 'expenses', 'name' => 'Gastos'],
            ['key' => 'reports', 'name' => 'Reportes de ventas y ganancias'],
            ['key' => 'multi_user', 'name' => 'Múltiples usuarios / empleados'],
        ];

        foreach ($features as $index => $feature) {
            Feature::updateOrCreate(
                ['key' => $feature['key']],
                ['name' => $feature['name'], 'sort_order' => $index],
            );
        }
    }
}
