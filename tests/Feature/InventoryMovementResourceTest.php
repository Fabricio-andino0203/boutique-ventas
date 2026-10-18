<?php

namespace Tests\Feature;

use App\Filament\Boutique\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Boutique\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class InventoryMovementResourceTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name = 'Comercio'): Tenant
    {
        return Tenant::create(['name' => $name, 'slug' => strtolower($name).'-'.uniqid()]);
    }

    private function boutiqueUser(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Vendedor',
            'email' => 'u'.uniqid().'@test.local',
            'password' => 'secret',
            'is_super_admin' => false,
            'is_active' => true,
        ]);
    }

    private function movement(Tenant $tenant, string $type = 'sale', int $quantity = -2): InventoryMovement
    {
        $product = Product::create(['name' => 'Prenda '.uniqid(), 'default_price' => 100, 'tenant_id' => $tenant->id]);
        $variant = $product->variants()->create([
            'tenant_id' => $tenant->id,
            'price' => 100,
            'cost' => 50,
            'stock_quantity' => 10,
        ]);

        return InventoryMovement::create([
            'tenant_id' => $tenant->id,
            'product_variant_id' => $variant->id,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => 8,
        ]);
    }

    public function test_la_lista_solo_trae_los_movimientos_del_comercio_actual(): void
    {
        $tenantA = $this->tenant('A');
        $tenantB = $this->tenant('B');

        $movementA = $this->movement($tenantA);
        $movementB = $this->movement($tenantB);

        Auth::login($this->boutiqueUser($tenantA));
        Filament::setCurrentPanel(Filament::getPanel('boutique'));

        Livewire::test(ListInventoryMovements::class)
            ->assertCanSeeTableRecords([$movementA])
            ->assertCanNotSeeTableRecords([$movementB]);
    }

    public function test_el_recurso_es_de_solo_lectura(): void
    {
        $tenant = $this->tenant();
        $movement = $this->movement($tenant);

        Auth::login($this->boutiqueUser($tenant));

        $this->assertFalse(InventoryMovementResource::canCreate());
        $this->assertFalse(InventoryMovementResource::canEdit($movement));
        $this->assertFalse(InventoryMovementResource::canDelete($movement));
    }

    public function test_el_recurso_solo_expone_listado_y_vista(): void
    {
        $pages = array_keys(InventoryMovementResource::getPages());

        $this->assertSame(['index', 'view'], $pages);
    }
}
