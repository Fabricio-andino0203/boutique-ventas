<?php

namespace Tests\Feature;

use App\Filament\Boutique\Resources\Orders\Pages\CreateOrder;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OrderService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class PedidosTest extends TestCase
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

    /**
     * @return array{order: Order, variant: ProductVariant}
     */
    private function pedidoConItem(int $stock = 10, int $cantidad = 2, float $precio = 500): array
    {
        $product = Product::create(['name' => 'Vestido', 'default_price' => $precio]);
        $variant = $product->variants()->create(['price' => $precio, 'cost' => 200, 'stock_quantity' => $stock]);

        $order = Order::create([
            'user_id' => Auth::id(),
            'type' => 'recogida',
            'status' => 'pending',
            'origin' => 'pedido',
            'discount_type' => 'fixed',
            'discount_value' => 0,
        ]);
        $order->items()->create([
            'product_variant_id' => $variant->id,
            'name' => 'Vestido',
            'quantity' => $cantidad,
            'unit_price' => $precio,
        ]);

        return ['order' => $order, 'variant' => $variant];
    }

    public function test_el_pedido_recibe_numero_al_crearse(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $order = Order::create([
            'user_id' => Auth::id(),
            'type' => 'recogida',
            'status' => 'pending',
            'origin' => 'pedido',
            'discount_type' => 'fixed',
            'discount_value' => 0,
        ]);

        $this->assertSame('P-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT), $order->number);
        $this->assertSame($tenant->id, $order->tenant_id);
    }

    public function test_el_item_sin_nombre_lo_deriva_de_la_variante(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $product = Product::create(['name' => 'Blusa de prueba', 'default_price' => 500]);
        $variant = $product->variants()->create(['price' => 500, 'cost' => 200, 'stock_quantity' => 5]);

        $order = Order::create([
            'user_id' => Auth::id(),
            'type' => 'recogida',
            'status' => 'pending',
            'origin' => 'pedido',
            'discount_type' => 'fixed',
            'discount_value' => 0,
        ]);
        $item = $order->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 500,
        ]);

        $this->assertSame('Blusa de prueba', $item->fresh()->name);
        $this->assertSame(500.0, (float) $item->fresh()->subtotal);
    }

    public function test_recompute_totals_calcula_total_y_deuda(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        ['order' => $order] = $this->pedidoConItem(cantidad: 2, precio: 500);

        app(OrderService::class)->recomputeTotals($order);
        $order->refresh();

        $this->assertSame(1000.0, (float) $order->total);
        $this->assertSame(1000.0, (float) $order->due);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_abono_parcial_deja_el_pedido_en_parcial_sin_generar_venta(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        ['order' => $order, 'variant' => $variant] = $this->pedidoConItem(stock: 10, cantidad: 2, precio: 500);
        $service = app(OrderService::class);
        $service->recomputeTotals($order);

        $service->registerPayment($order->fresh(), 400, 'cash');
        $order->refresh();

        $this->assertSame('partial', $order->payment_status);
        $this->assertSame(600.0, (float) $order->due);
        $this->assertNull($order->sale_id);
        $this->assertSame(10, $variant->fresh()->stock_quantity);
    }

    public function test_pago_total_genera_venta_y_descuenta_stock(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        ['order' => $order, 'variant' => $variant] = $this->pedidoConItem(stock: 10, cantidad: 2, precio: 500);
        $service = app(OrderService::class);
        $service->recomputeTotals($order);

        $service->registerPayment($order->fresh(), 1000, 'cash');
        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->sale_id);
        $this->assertSame(8, $variant->fresh()->stock_quantity);

        // No debe duplicar la venta si vuelve a saldarse (idempotente al estar ya ligado).
        $service->registerPayment($order->fresh(), 100, 'cash');
        $this->assertSame(8, $variant->fresh()->stock_quantity);
    }

    public function test_crear_pedido_con_abono_sin_stock_deja_el_pedido_creado(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));
        Filament::setCurrentPanel(Filament::getPanel('boutique'));

        $product = Product::create(['name' => 'Agotado', 'default_price' => 500]);
        $variant = $product->variants()->create(['price' => 500, 'cost' => 200, 'stock_quantity' => 0]);

        Livewire::test(CreateOrder::class)
            ->fillForm([
                'type' => 'recogida',
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1, 'unit_price' => 500],
                ],
                'abono' => 500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $order = Order::query()->latest('id')->first();
        $this->assertNotNull($order);
        $this->assertNull($order->sale_id);
        $this->assertSame(0.0, (float) $order->paid);
        $this->assertSame('unpaid', $order->payment_status);
    }

    public function test_no_genera_venta_si_no_hay_stock_suficiente(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        ['order' => $order, 'variant' => $variant] = $this->pedidoConItem(stock: 1, cantidad: 2, precio: 500);
        $service = app(OrderService::class);
        $service->recomputeTotals($order);

        try {
            $service->registerPayment($order->fresh(), 1000, 'cash');
            $this->fail('Se esperaba una excepción por falta de existencias.');
        } catch (\Throwable $e) {
            // La transacción debe revertir: ni pago ni venta quedan registrados.
        }

        $order->refresh();
        $this->assertNull($order->sale_id);
        $this->assertSame(0.0, (float) $order->paid);
        $this->assertSame(1, $variant->fresh()->stock_quantity);
    }
}
