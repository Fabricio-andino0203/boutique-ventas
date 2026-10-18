<?php

namespace Tests\Feature;

use App\Actions\RegisterReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DevolucionesTest extends TestCase
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
            'name' => 'Cajera',
            'email' => 'u'.uniqid().'@test.local',
            'password' => 'secret',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        Auth::login($user);

        return $user;
    }

    private function variant(int $stock = 2): ProductVariant
    {
        $product = Product::create(['name' => 'Vestido '.uniqid(), 'default_price' => 500]);

        return $product->variants()->create(['price' => 500, 'cost' => 200, 'stock_quantity' => $stock]);
    }

    private function saleOf(ProductVariant $variant, int $quantity, float $unitPrice = 500.0): Sale
    {
        $sale = Sale::create([
            'number' => 'V-'.uniqid(),
            'status' => 'completed',
            'subtotal' => $unitPrice * $quantity,
            'total' => $unitPrice * $quantity,
            'payment_method' => 'cash',
            'amount_paid' => $unitPrice * $quantity,
            'sold_at' => now(),
        ]);

        $sale->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => 200,
            'discount' => 0,
            'subtotal' => $unitPrice * $quantity,
        ]);

        return $sale;
    }

    public function test_devolucion_reingresa_stock_y_crea_movimiento(): void
    {
        $tenant = $this->tenant();
        $this->loginFor($tenant);

        $variant = $this->variant(2);
        $sale = $this->saleOf($variant, quantity: 5);

        $return = app(RegisterReturn::class)->execute(
            $sale,
            [['variant_id' => $variant->id, 'quantity' => 2]],
            ['reason' => 'Talla equivocada'],
        );

        $this->assertSame($tenant->id, $return->tenant_id);
        $this->assertSame($sale->id, $return->sale_id);
        $this->assertSame('D-'.str_pad((string) $return->id, 6, '0', STR_PAD_LEFT), $return->number);
        $this->assertEquals(1000.0, (float) $return->total);
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(1, $return->items()->count());

        $this->assertDatabaseHas('inventory_movements', [
            'product_variant_id' => $variant->id,
            'type' => 'return',
            'quantity' => 2,
            'source_type' => SaleReturn::class,
            'source_id' => $return->id,
            'balance_after' => 4,
        ]);
    }

    public function test_devolucion_rechaza_devolver_mas_de_lo_vendido(): void
    {
        $tenant = $this->tenant();
        $this->loginFor($tenant);

        $variant = $this->variant(2);
        $sale = $this->saleOf($variant, quantity: 3);

        $this->expectException(ValidationException::class);

        try {
            app(RegisterReturn::class)->execute(
                $sale,
                [['variant_id' => $variant->id, 'quantity' => 4]],
            );
        } finally {
            $this->assertSame(2, $variant->fresh()->stock_quantity);
            $this->assertSame(0, SaleReturn::count());
        }
    }

    public function test_devolucion_acumula_contra_devoluciones_previas(): void
    {
        $this->loginFor($this->tenant());

        $variant = $this->variant(0);
        $sale = $this->saleOf($variant, quantity: 5);

        app(RegisterReturn::class)->execute($sale, [['variant_id' => $variant->id, 'quantity' => 3]]);

        $this->expectException(ValidationException::class);

        app(RegisterReturn::class)->execute($sale, [['variant_id' => $variant->id, 'quantity' => 3]]);
    }

    public function test_devolucion_se_aisla_por_comercio(): void
    {
        $tenantA = $this->tenant('A');
        $this->loginFor($tenantA);

        $variant = $this->variant(2);
        $sale = $this->saleOf($variant, quantity: 4);

        app(RegisterReturn::class)->execute($sale, [['variant_id' => $variant->id, 'quantity' => 1]]);

        $this->assertSame(1, SaleReturn::count());

        $tenantB = $this->tenant('B');
        $this->loginFor($tenantB);

        $this->assertSame(0, SaleReturn::count());
    }
}
