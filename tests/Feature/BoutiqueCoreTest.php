<?php

namespace Tests\Feature;

use App\Actions\RegisterSale;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BoutiqueCoreTest extends TestCase
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

    public function test_super_admin_accede_admin_pero_no_boutique(): void
    {
        $admin = User::create([
            'name' => 'Dueño', 'email' => 'admin@test.local', 'password' => 'secret',
            'is_super_admin' => true, 'is_active' => true, 'tenant_id' => null,
        ]);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('boutique')));
    }

    public function test_usuario_boutique_no_accede_admin(): void
    {
        $user = $this->boutiqueUser($this->tenant());

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('boutique')));
    }

    public function test_usuario_inactivo_no_accede_ningun_panel(): void
    {
        $user = $this->boutiqueUser($this->tenant());
        $user->update(['is_active' => false]);

        $this->assertFalse($user->fresh()->canAccessPanel(Filament::getPanel('boutique')));
    }

    public function test_crear_registro_asigna_el_comercio_del_usuario(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $category = Category::create(['name' => 'Vestidos']);

        $this->assertSame($tenant->id, $category->tenant_id);
    }

    public function test_el_scope_aisla_los_datos_por_comercio(): void
    {
        $tenantA = $this->tenant('A');
        $tenantB = $this->tenant('B');
        Category::create(['name' => 'Cat A', 'tenant_id' => $tenantA->id]);
        Category::create(['name' => 'Cat B', 'tenant_id' => $tenantB->id]);

        Auth::login($this->boutiqueUser($tenantA));

        $this->assertSame(1, Category::count());
        $this->assertSame('Cat A', Category::first()->name);
    }

    public function test_registrar_venta_descuenta_stock_y_crea_movimiento(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $product = Product::create(['name' => 'Blusa', 'default_price' => 250]);
        $variant = $product->variants()->create(['price' => 250, 'cost' => 100, 'stock_quantity' => 10]);

        $sale = app(RegisterSale::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 3, 'unit_price' => 250.0]],
            ['payment_method' => 'cash', 'amount_paid' => 750.0],
        );

        $this->assertSame($tenant->id, $sale->tenant_id);
        $this->assertEquals(750.0, (float) $sale->total);
        $this->assertSame(7, $variant->fresh()->stock_quantity);
        $this->assertSame(1, $sale->items()->count());
        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'sale',
            'quantity' => -3,
        ]);
    }

    public function test_registrar_venta_rechaza_stock_insuficiente(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $product = Product::create(['name' => 'Blusa', 'default_price' => 250]);
        $variant = $product->variants()->create(['price' => 250, 'cost' => 100, 'stock_quantity' => 2]);

        $this->expectException(ValidationException::class);

        app(RegisterSale::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 5, 'unit_price' => 250.0]],
            ['payment_method' => 'cash', 'amount_paid' => 1250.0],
        );

        $this->assertSame(2, $variant->fresh()->stock_quantity);
    }
}
