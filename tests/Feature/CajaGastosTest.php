<?php

namespace Tests\Feature;

use App\Actions\CloseCashRegister;
use App\Actions\OpenCashRegister;
use App\Actions\RegisterSale;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CajaGastosTest extends TestCase
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

    private function variantWithStock(int $stock = 10, float $price = 250): ProductVariant
    {
        $product = Product::create(['name' => 'Blusa', 'default_price' => $price]);

        return $product->variants()->create(['price' => $price, 'cost' => 100, 'stock_quantity' => $stock]);
    }

    // -------------------- Gastos --------------------

    public function test_crear_gasto_asigna_el_comercio_del_usuario(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $expense = Expense::create([
            'description' => 'Alquiler del local',
            'amount' => 8500,
            'payment_method' => 'transfer',
            'spent_at' => now(),
        ]);

        $this->assertSame($tenant->id, $expense->tenant_id);
    }

    public function test_el_scope_aisla_los_gastos_por_comercio(): void
    {
        $tenantA = $this->tenant('A');
        $tenantB = $this->tenant('B');
        Expense::create(['description' => 'Gasto A', 'amount' => 100, 'tenant_id' => $tenantA->id]);
        Expense::create(['description' => 'Gasto B', 'amount' => 200, 'tenant_id' => $tenantB->id]);

        Auth::login($this->boutiqueUser($tenantA));

        $this->assertSame(1, Expense::count());
        $this->assertSame('Gasto A', Expense::first()->description);
    }

    // -------------------- Caja --------------------

    public function test_abrir_caja_crea_una_caja_abierta(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);
        Auth::login($user);

        $register = app(OpenCashRegister::class)->execute($user, 500.0);

        $this->assertSame('open', $register->status);
        $this->assertSame($user->id, $register->opened_by);
        $this->assertSame($tenant->id, $register->tenant_id);
        $this->assertEquals(500.0, (float) $register->opening_balance);
    }

    public function test_no_se_pueden_abrir_dos_cajas_para_el_mismo_usuario(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);
        Auth::login($user);

        app(OpenCashRegister::class)->execute($user, 500.0);

        $this->expectException(ValidationException::class);

        app(OpenCashRegister::class)->execute($user, 300.0);
    }

    public function test_cerrar_caja_calcula_el_efectivo_esperado(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);
        Auth::login($user);

        $register = app(OpenCashRegister::class)->execute($user, 500.0);
        $variant = $this->variantWithStock(10);

        // Venta en efectivo ligada a la caja (3 x 250 = 750).
        app(RegisterSale::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 3, 'unit_price' => 250.0]],
            ['payment_method' => 'cash', 'amount_paid' => 750.0],
        );

        // Venta con tarjeta: NO debe sumar al efectivo esperado.
        app(RegisterSale::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 250.0]],
            ['payment_method' => 'card', 'amount_paid' => 250.0],
        );

        $closed = app(CloseCashRegister::class)->execute($register->fresh(), 1250.0);

        $this->assertSame('closed', $closed->status);
        $this->assertEquals(1250.0, (float) $closed->expected_balance); // 500 + 750
        $this->assertEquals(1250.0, (float) $closed->closing_balance);
        $this->assertEquals(0.0, (float) $closed->difference);
        $this->assertSame($user->id, $closed->closed_by);
    }

    public function test_una_venta_con_caja_abierta_queda_ligada(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);
        Auth::login($user);

        $register = app(OpenCashRegister::class)->execute($user, 0.0);
        $variant = $this->variantWithStock(5);

        $sale = app(RegisterSale::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 250.0]],
            ['payment_method' => 'cash', 'amount_paid' => 250.0],
        );

        $this->assertSame($register->id, $sale->cash_register_id);
    }

    public function test_una_venta_sin_caja_abierta_no_queda_ligada(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $variant = $this->variantWithStock(5);

        $sale = app(RegisterSale::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 250.0]],
            ['payment_method' => 'cash', 'amount_paid' => 250.0],
        );

        $this->assertNull($sale->cash_register_id);
    }

    public function test_el_scope_aisla_las_cajas_por_comercio(): void
    {
        $tenantA = $this->tenant('A');
        $tenantB = $this->tenant('B');
        $userA = $this->boutiqueUser($tenantA);

        Auth::login($userA);
        app(OpenCashRegister::class)->execute($userA, 500.0);

        Auth::login($this->boutiqueUser($tenantB));

        $this->assertSame(0, CashRegister::count());
    }
}
