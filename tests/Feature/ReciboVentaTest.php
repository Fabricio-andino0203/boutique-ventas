<?php

namespace Tests\Feature;

use App\Actions\RegisterSale;
use App\Filament\Boutique\Pages\Pos;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class ReciboVentaTest extends TestCase
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
            'name' => 'Vendedora',
            'email' => 'u'.uniqid().'@test.local',
            'password' => 'secret',
            'is_super_admin' => false,
            'is_active' => true,
        ]);
    }

    private function registrarVenta(User $user): Sale
    {
        Auth::login($user);

        $product = Product::create(['name' => 'Blusa Rosa', 'default_price' => 250]);
        $variant = $product->variants()->create(['price' => 250, 'cost' => 100, 'stock_quantity' => 10]);

        $sale = app(RegisterSale::class)->execute(
            [['variant_id' => $variant->id, 'quantity' => 3, 'unit_price' => 250.0]],
            ['payment_method' => 'cash', 'amount_paid' => 800.0],
        );

        Auth::logout();

        return $sale;
    }

    public function test_recibo_de_la_propia_venta_muestra_numero_items_y_total(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);
        $sale = $this->registrarVenta($user);

        $response = $this->actingAs($user)->get(route('boutique.sales.receipt', $sale));

        $response->assertOk();
        $response->assertSee($sale->number);
        $response->assertSee('Blusa Rosa');
        $response->assertSee('L 250.00'); // precio unitario
        $response->assertSee('L 750.00'); // total
        $response->assertSee('¡Gracias por su compra!', false);
    }

    public function test_recibo_rechaza_la_venta_de_otro_comercio(): void
    {
        $tenantA = $this->tenant('A');
        $userA = $this->boutiqueUser($tenantA);

        $tenantB = $this->tenant('B');
        $userB = $this->boutiqueUser($tenantB);
        $saleB = $this->registrarVenta($userB);

        $response = $this->actingAs($userA)->get(route('boutique.sales.receipt', $saleB));

        $response->assertNotFound();
    }

    public function test_recibo_requiere_autenticacion(): void
    {
        $tenant = $this->tenant();
        $sale = $this->registrarVenta($this->boutiqueUser($tenant));

        $response = $this->get(route('boutique.sales.receipt', $sale));

        $response->assertRedirect();
    }

    public function test_pos_registra_venta_y_ofrece_recibo(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);
        $this->actingAs($user);

        $product = Product::create(['name' => 'Falda', 'default_price' => 300]);
        $variant = $product->variants()->create([
            'price' => 300,
            'cost' => 120,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        Livewire::test(Pos::class)
            ->call('addToCart', $variant->id)
            ->set('amountPaid', 300)
            ->call('register')
            ->assertHasNoErrors()
            ->assertNotified();

        $this->assertDatabaseHas('sales', [
            'tenant_id' => $tenant->id,
            'total' => 300,
        ]);
    }
}
