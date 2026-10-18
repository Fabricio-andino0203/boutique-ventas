<?php

namespace Tests\Feature;

use App\Actions\RegisterPurchase;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComprasTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name = 'Comercio'): Tenant
    {
        return Tenant::create(['name' => $name, 'slug' => strtolower($name).'-'.uniqid()]);
    }

    private function loginFor(Tenant $tenant): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Compradora',
            'email' => 'u'.uniqid().'@test.local',
            'password' => 'secret',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        Auth::login($user);

        return $user;
    }

    private function variant(int $stock = 5): ProductVariant
    {
        $product = Product::create(['name' => 'Blusa '.uniqid(), 'default_price' => 250]);

        return $product->variants()->create(['price' => 250, 'cost' => 100, 'stock_quantity' => $stock]);
    }

    public function test_registrar_compra_incrementa_stock_y_crea_movimiento(): void
    {
        $tenant = $this->tenant();
        $this->loginFor($tenant);

        $supplier = Supplier::create(['name' => 'Proveedor X']);
        $variant = $this->variant(5);

        $purchase = app(RegisterPurchase::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 8, 'unit_cost' => 90.0]],
            ['supplier_id' => $supplier->id, 'notes' => 'Reposición'],
        );

        $this->assertSame($tenant->id, $purchase->tenant_id);
        $this->assertSame('received', $purchase->status);
        $this->assertSame('C-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT), $purchase->number);
        $this->assertEquals(720.0, (float) $purchase->total);
        $this->assertSame(13, $variant->fresh()->stock_quantity);
        $this->assertSame(1, $purchase->items()->count());

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'purchase',
            'quantity' => 8,
            'source_type' => Purchase::class,
            'source_id' => $purchase->id,
            'balance_after' => 13,
        ]);
    }

    public function test_compra_sin_lineas_valida(): void
    {
        $this->loginFor($this->tenant());

        $this->expectException(ValidationException::class);

        app(RegisterPurchase::class)->execute([]);
    }

    public function test_compra_se_aisla_por_comercio(): void
    {
        $tenantA = $this->tenant('A');
        $this->loginFor($tenantA);
        $variantA = $this->variant(5);

        app(RegisterPurchase::class)->execute(
            [['variant_id' => $variantA->id, 'quantity' => 3, 'unit_cost' => 100.0]],
        );

        $this->assertSame(1, Purchase::count());

        $tenantB = $this->tenant('B');
        $this->loginFor($tenantB);

        $this->assertSame(0, Purchase::count());
    }
}
