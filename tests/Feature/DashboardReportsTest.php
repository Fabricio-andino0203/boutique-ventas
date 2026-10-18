<?php

namespace Tests\Feature;

use App\Filament\Boutique\Widgets\SalesChart;
use App\Filament\Boutique\Widgets\StatsOverview;
use App\Filament\Boutique\Widgets\TopProducts;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardReportsTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name = 'Comercio'): Tenant
    {
        $tenant = Tenant::create(['name' => $name, 'slug' => strtolower($name).'-'.uniqid()]);

        // El acceso al panel de boutique exige una suscripción vigente.
        $plan = Plan::create([
            'name' => 'Mensual',
            'slug' => 'mensual-'.uniqid(),
            'price' => 500,
            'duration_days' => 30,
        ]);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
            'price' => 500,
            'currency' => 'HNL',
        ]);

        return $tenant;
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

    private function variant(float $price = 100, int $stock = 20): ProductVariant
    {
        $product = Product::create(['name' => 'Prenda '.uniqid(), 'default_price' => $price]);

        return $product->variants()->create(['price' => $price, 'cost' => 50, 'stock_quantity' => $stock]);
    }

    private function sale(float $total, Carbon $soldAt, string $status = 'completed'): Sale
    {
        return Sale::create([
            'user_id' => Auth::id(),
            'number' => 'V-'.uniqid(),
            'status' => $status,
            'subtotal' => $total,
            'discount' => 0,
            'tax' => 0,
            'total' => $total,
            'payment_method' => 'cash',
            'amount_paid' => $total,
            'change' => 0,
            'sold_at' => $soldAt,
        ]);
    }

    private function item(Sale $sale, ProductVariant $variant, int $quantity, float $unitPrice): SaleItem
    {
        return SaleItem::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => 0,
            'discount' => 0,
            'subtotal' => $quantity * $unitPrice,
        ]);
    }

    /**
     * Invoca un método protegido/privado del widget para inspeccionar su cálculo.
     */
    private function invoke(object $widget, string $method): mixed
    {
        return (fn () => $this->{$method}())->call($widget);
    }

    public function test_stats_overview_agrega_totales_del_comercio(): void
    {
        $tenant = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenant));

        $this->sale(1000, Carbon::now());
        $this->sale(500, Carbon::now());
        $this->sale(2000, Carbon::now()->startOfMonth()->addDay());
        $this->sale(999, Carbon::now(), 'voided'); // anulada: no cuenta

        $this->variant(100, 3); // bajo stock
        $this->variant(100, 5); // bajo stock (límite)
        $this->variant(100, 10); // ok

        $stats = $this->invoke(new StatsOverview, 'getStats');

        // Ventas de hoy = 1000 + 500 = 1500 (excluye anulada y la del inicio de mes si no es hoy).
        $ventasHoy = Carbon::now()->isSameDay(Carbon::now()->startOfMonth()->addDay()) ? 3500.0 : 1500.0;
        $this->assertSame('L '.number_format($ventasHoy, 2), $stats[0]->getValue());

        // Ventas del mes = 1000 + 500 + 2000 = 3500 (excluye anulada).
        $this->assertSame('L 3,500.00', $stats[1]->getValue());

        // Variantes bajo stock (<= 5) = 2.
        $this->assertSame('2', $stats[3]->getValue());
    }

    public function test_stats_overview_no_incluye_datos_de_otro_comercio(): void
    {
        $tenantB = $this->tenant('B');
        Auth::login($this->boutiqueUser($tenantB));
        $this->sale(9999, Carbon::now());
        $this->variant(100, 1); // bajo stock de B

        $tenantA = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenantA));
        $this->sale(1000, Carbon::now());

        $stats = $this->invoke(new StatsOverview, 'getStats');

        $this->assertSame('L 1,000.00', $stats[1]->getValue()); // mes de A, sin los 9999 de B
        $this->assertSame('0', $stats[3]->getValue()); // A no tiene variantes bajo stock
    }

    public function test_top_products_ordena_y_aisla_por_comercio(): void
    {
        $tenantB = $this->tenant('B');
        Auth::login($this->boutiqueUser($tenantB));
        $variantB = $this->variant();
        $this->item($this->sale(5000, Carbon::now()), $variantB, 50, 100);

        $tenantA = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenantA));
        $masVendido = $this->variant();
        $menosVendido = $this->variant();
        $fueraDeRango = $this->variant();

        $this->item($this->sale(300, Carbon::now()), $masVendido, 8, 100);
        $this->item($this->sale(300, Carbon::now()->subDays(3)), $masVendido, 2, 100);
        $this->item($this->sale(300, Carbon::now()), $menosVendido, 3, 100);
        // Venta de hace 40 días: fuera de la ventana de 30 días.
        $this->item($this->sale(300, Carbon::now()->subDays(40)), $fueraDeRango, 99, 100);

        $top = $this->invoke(new TopProducts, 'productosMasVendidos');

        $this->assertCount(2, $top); // solo variantes vendidas dentro de la ventana
        $this->assertSame($masVendido->id, $top[0]['id']);
        $this->assertSame(10, $top[0]['unidades']); // 8 + 2
        $this->assertSame($menosVendido->id, $top[1]['id']);
        $this->assertSame(3, $top[1]['unidades']);
        $this->assertNotContains($variantB->id, $top->pluck('id')->all());
    }

    public function test_sales_chart_suma_por_dia_y_aisla_por_comercio(): void
    {
        $tenantB = $this->tenant('B');
        Auth::login($this->boutiqueUser($tenantB));
        $this->sale(7777, Carbon::now());

        $tenantA = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenantA));
        $this->sale(1000, Carbon::now());
        $this->sale(250, Carbon::now());
        $this->sale(400, Carbon::now()->subDays(5));
        $this->sale(600, Carbon::now()->subDays(20)); // fuera de la ventana de 14 días

        $data = $this->invoke(new SalesChart, 'getData');

        $this->assertCount(14, $data['labels']);
        $this->assertCount(14, $data['datasets'][0]['data']);

        $serie = $data['datasets'][0]['data'];
        $this->assertSame(1250.0, $serie[13]); // hoy: 1000 + 250
        $this->assertSame(400.0, $serie[8]);   // hace 5 días
        // Total de A dentro de la ventana de 14 días: sin B (7777) ni la venta de hace 20 días (600).
        $this->assertSame(1650.0, array_sum($serie));
    }

    public function test_dashboard_responde_para_usuario_de_boutique(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);

        $response = $this->actingAs($user)->get('/tienda');

        $response->assertOk();
    }

    public function test_los_tres_widgets_renderizan_sin_errores(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));
        Filament::setCurrentPanel(Filament::getPanel('boutique'));

        $variant = $this->variant();
        $this->item($this->sale(300, Carbon::now()), $variant, 3, 100);

        Livewire::test(StatsOverview::class)->assertOk();
        Livewire::test(TopProducts::class)->assertOk();
        Livewire::test(SalesChart::class)->assertOk();
    }

    public function test_los_widgets_estan_registrados_en_el_panel(): void
    {
        $widgets = Filament::getPanel('boutique')->getWidgets();

        $this->assertContains(StatsOverview::class, $widgets);
        $this->assertContains(TopProducts::class, $widgets);
        $this->assertContains(SalesChart::class, $widgets);
    }
}
