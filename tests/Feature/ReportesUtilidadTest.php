<?php

namespace Tests\Feature;

use App\Filament\Boutique\Pages\Reportes;
use App\Filament\Boutique\Reportes\ProfitByDayChart;
use App\Filament\Boutique\Reportes\ProfitStats;
use App\Filament\Boutique\Reportes\SellerProfit;
use App\Filament\Boutique\Reportes\TopProfitableProducts;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class ReportesUtilidadTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name = 'Comercio'): Tenant
    {
        return Tenant::create(['name' => $name, 'slug' => strtolower($name).'-'.uniqid()]);
    }

    private function boutiqueUser(Tenant $tenant, string $name = 'Vendedor'): User
    {
        return User::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'email' => 'u'.uniqid().'@test.local',
            'password' => 'secret',
            'is_super_admin' => false,
            'is_active' => true,
        ]);
    }

    private function variant(float $price = 100, float $cost = 50, int $stock = 100): ProductVariant
    {
        $product = Product::create(['name' => 'Prenda '.uniqid(), 'default_price' => $price]);

        return $product->variants()->create(['price' => $price, 'cost' => $cost, 'stock_quantity' => $stock]);
    }

    private function sale(float $total, Carbon $soldAt, ?int $userId = null, string $status = 'completed'): Sale
    {
        return Sale::create([
            'user_id' => $userId ?? Auth::id(),
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
     * Prepara un widget con los filtros del mes en curso e invoca un método
     * protegido para inspeccionar su cálculo.
     */
    private function calcular(string $widgetClass, string $metodo): mixed
    {
        $widget = new $widgetClass;
        $widget->pageFilters = [
            'desde' => Carbon::now()->startOfMonth()->toDateString(),
            'hasta' => Carbon::now()->endOfMonth()->toDateString(),
        ];

        return (fn () => $this->{$metodo}())->call($widget);
    }

    public function test_metricas_calculan_utilidad_del_periodo(): void
    {
        $tenant = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenant));

        $v1 = $this->variant(100, 50);
        $v2 = $this->variant(60, 20);

        $this->item($this->sale(300, Carbon::now()), $v1, 3, 100); // ingreso 300, costo 150
        $this->item($this->sale(120, Carbon::now()), $v2, 2, 60);  // ingreso 120, costo 40

        $metricas = $this->calcular(ProfitStats::class, 'metricas');

        $this->assertSame(420.0, $metricas['ventas']);
        $this->assertSame(190.0, $metricas['costo']);        // 150 + 40
        $this->assertSame(230.0, $metricas['utilidad']);     // 420 - 190
        $this->assertSame(2, $metricas['cantidad']);
        $this->assertSame(210.0, $metricas['ticket']);       // 420 / 2
        $this->assertEqualsWithDelta(54.76, $metricas['margen'], 0.01); // 230/420*100
    }

    public function test_ventas_anuladas_y_fuera_de_rango_no_cuentan(): void
    {
        $tenant = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenant));

        $v = $this->variant(100, 50);

        $this->item($this->sale(300, Carbon::now()), $v, 3, 100);                 // dentro del rango
        $this->item($this->sale(500, Carbon::now()->subMonths(2)), $v, 5, 100);   // fuera del rango
        $this->item($this->sale(999, Carbon::now(), null, 'voided'), $v, 9, 100); // anulada

        $metricas = $this->calcular(ProfitStats::class, 'metricas');

        $this->assertSame(300.0, $metricas['ventas']);
        $this->assertSame(150.0, $metricas['costo']);
        $this->assertSame(150.0, $metricas['utilidad']);
        $this->assertSame(1, $metricas['cantidad']);
    }

    public function test_reportes_aisla_por_comercio(): void
    {
        $tenantB = $this->tenant('B');
        Auth::login($this->boutiqueUser($tenantB));
        $vB = $this->variant(500, 100);
        $this->item($this->sale(5000, Carbon::now()), $vB, 10, 500); // datos de B

        $tenantA = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenantA));
        $vA = $this->variant(100, 50);
        $this->item($this->sale(300, Carbon::now()), $vA, 3, 100); // datos de A

        $metricas = $this->calcular(ProfitStats::class, 'metricas');

        // Solo refleja A: ventas 300, costo 150, utilidad 150. Nada de los 5000 de B.
        $this->assertSame(300.0, $metricas['ventas']);
        $this->assertSame(150.0, $metricas['costo']);
        $this->assertSame(150.0, $metricas['utilidad']);

        /** @var Collection<int, array<string, mixed>> $productos */
        $productos = $this->calcular(TopProfitableProducts::class, 'productosRentables');
        $this->assertCount(1, $productos);
        $this->assertSame($vA->product->name, $productos[0]['producto']);
    }

    public function test_productos_rentables_ordena_por_utilidad(): void
    {
        $tenant = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenant));

        $rentable = $this->variant(100, 50);   // utilidad unitaria 50
        $menos = $this->variant(60, 40);        // utilidad unitaria 20

        $this->item($this->sale(300, Carbon::now()), $rentable, 3, 100); // utilidad 150
        $this->item($this->sale(120, Carbon::now()), $menos, 2, 60);     // utilidad 40

        /** @var Collection<int, array<string, mixed>> $productos */
        $productos = $this->calcular(TopProfitableProducts::class, 'productosRentables');

        $this->assertCount(2, $productos);
        $this->assertSame($rentable->product->name, $productos[0]['producto']);
        $this->assertSame(150.0, $productos[0]['utilidad']);
        $this->assertSame(300.0, $productos[0]['ingreso']);
        $this->assertSame(150.0, $productos[0]['costo']);
        $this->assertSame(3, $productos[0]['unidades']);
        $this->assertSame($menos->product->name, $productos[1]['producto']);
        $this->assertSame(40.0, $productos[1]['utilidad']);
    }

    public function test_ventas_por_vendedor_agrega_por_usuario(): void
    {
        $tenant = $this->tenant('A');
        $ana = $this->boutiqueUser($tenant, 'Ana');
        $beto = $this->boutiqueUser($tenant, 'Beto');
        Auth::login($ana);

        $v = $this->variant(100, 50);

        $this->item($this->sale(300, Carbon::now(), $ana->id), $v, 3, 100);  // Ana: ventas 300, costo 150, util 150
        $this->item($this->sale(200, Carbon::now(), $ana->id), $v, 2, 100);  // Ana: ventas 200, costo 100, util 100
        $this->item($this->sale(100, Carbon::now(), $beto->id), $v, 1, 100); // Beto: ventas 100, costo 50, util 50

        /** @var Collection<int, array<string, mixed>> $vendedores */
        $vendedores = $this->calcular(SellerProfit::class, 'ventasPorVendedor');
        $porNombre = $vendedores->keyBy('vendedor');

        $this->assertSame(500.0, $porNombre['Ana']['ventas']);
        $this->assertSame(2, $porNombre['Ana']['cantidad']);
        $this->assertSame(250.0, $porNombre['Ana']['utilidad']); // 500 - 250
        $this->assertSame(100.0, $porNombre['Beto']['ventas']);
        $this->assertSame(50.0, $porNombre['Beto']['utilidad']); // 100 - 50
    }

    public function test_grafica_ventas_por_dia_respeta_el_rango(): void
    {
        $tenant = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenant));

        $this->sale(1000, Carbon::now()->startOfMonth());
        $this->sale(250, Carbon::now()->startOfMonth());

        $data = $this->calcular(ProfitByDayChart::class, 'getData');

        // Una etiqueta y un dato por cada día del mes en curso.
        $this->assertSame(Carbon::now()->daysInMonth, count($data['labels']));
        $this->assertSame($data['labels'] !== [] ? 1250.0 : 0.0, $data['datasets'][0]['data'][0]);
    }

    public function test_pagina_de_reportes_renderiza_con_widgets(): void
    {
        $tenant = $this->tenant('A');
        Auth::login($this->boutiqueUser($tenant));
        Filament::setCurrentPanel(Filament::getPanel('boutique'));

        $v = $this->variant(100, 50);
        $this->item($this->sale(300, Carbon::now()), $v, 3, 100);

        Livewire::test(Reportes::class)
            ->assertOk()
            ->assertSee('Utilidad bruta')
            ->assertSee('Productos más rentables')
            ->assertSee('Ventas por vendedor');
    }

    public function test_pagina_responde_por_http_para_boutique(): void
    {
        $tenant = $this->tenant('A');
        $tenant->update(['status' => 'trial', 'trial_ends_at' => Carbon::now()->addDays(10)]);
        $user = $this->boutiqueUser($tenant);

        $this->actingAs($user)->get('/tienda/reportes')->assertOk();
    }
}
