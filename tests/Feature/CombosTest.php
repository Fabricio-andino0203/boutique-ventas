<?php

namespace Tests\Feature;

use App\Actions\RegisterSale;
use App\Filament\Boutique\Pages\Pos;
use App\Filament\Boutique\Resources\Combos\Pages\CreateCombo;
use App\Filament\Boutique\Resources\Combos\Pages\ListCombos;
use App\Models\Combo;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ComboPricing;
use App\Services\ComboService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CombosTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name = 'Comercio'): Tenant
    {
        return Tenant::create(['name' => $name, 'slug' => strtolower($name).'-'.uniqid()]);
    }

    private function loginTenant(string $name = 'Comercio'): Tenant
    {
        $tenant = $this->tenant($name);
        Auth::login(User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Vendedor',
            'email' => 'u'.uniqid().'@test.local',
            'password' => 'secret',
            'is_super_admin' => false,
            'is_active' => true,
        ]));

        return $tenant;
    }

    private function variant(float $price, float $cost, int $stock, string $type = 'simple'): ProductVariant
    {
        $product = Product::create(['type' => $type, 'name' => 'Prod '.uniqid(), 'default_price' => $price]);

        return $product->variants()->create([
            'name' => 'Var '.uniqid(),
            'price' => $price,
            'cost' => $cost,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }

    /**
     * Combo típico: un producto fijo + un producto "a elegir" entre dos variantes.
     *
     * @return array{combo: Combo, fixed: ProductVariant, cheap: ProductVariant, pricey: ProductVariant}
     */
    private function sampleCombo(string $pricingMode = 'cheapest', string $stockMode = 'derived', float $price = 250, ?float $basePrice = null): array
    {
        $fixed = $this->variant(100, 50, 10);
        $cheap = $this->variant(200, 100, 5, 'variable');
        // Segunda variante del mismo producto que la "a elegir" barata.
        $pricey = $cheap->product->variants()->create([
            'name' => 'Var cara', 'price' => 300, 'cost' => 150, 'stock_quantity' => 8, 'is_active' => true,
        ]);

        $combo = app(ComboService::class)->create([
            'name' => 'Combo Prueba',
            'pricing_mode' => $pricingMode,
            'price' => $price,
            'base_price' => $basePrice,
            'stock_mode' => $stockMode,
            'stock_quantity' => $stockMode === 'own' ? 20 : 0,
            'min_stock' => 2,
            'items' => [
                ['product_id' => $fixed->product_id, 'choose_at_sale' => false, 'product_variant_id' => $fixed->id, 'quantity' => 1],
                ['product_id' => $cheap->product_id, 'choose_at_sale' => true, 'quantity' => 1],
            ],
        ]);

        return ['combo' => $combo->fresh('items'), 'fixed' => $fixed, 'cheap' => $cheap, 'pricey' => $pricey];
    }

    public function test_crear_combo_genera_producto_y_variante_de_respaldo(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'fixed' => $fixed] = $this->sampleCombo();

        $this->assertSame('combo', $combo->product->type);
        $this->assertNotNull($combo->variant);
        $this->assertEqualsWithDelta(250.0, (float) $combo->variant->price, 0.01);
        // Costo representativo = 50 (fijo) + 100 (más barata elegible) = 150.
        $this->assertEqualsWithDelta(150.0, (float) $combo->variant->cost, 0.01);
        $this->assertCount(2, $combo->items);
        $this->assertTrue($combo->items->firstWhere('product_id', $fixed->product_id)->product_variant_id === $fixed->id);
    }

    public function test_por_separado_y_ahorro(): void
    {
        $this->loginTenant();
        ['combo' => $combo] = $this->sampleCombo(price: 250);
        $pricing = app(ComboPricing::class);

        // 100 (fijo) + 200 (más barata elegible) = 300.
        $this->assertEqualsWithDelta(300.0, $pricing->separate($combo), 0.01);
        $savings = $pricing->savings($combo);
        $this->assertEqualsWithDelta(50.0, $savings['amount'], 0.01);
        $this->assertEqualsWithDelta(16.7, $savings['percent'], 0.1);
    }

    public function test_stock_derivado_calculado(): void
    {
        $this->loginTenant();
        ['combo' => $combo] = $this->sampleCombo(stockMode: 'derived');
        // fijo: floor(10/1)=10 ; a elegir: max(5,8)=8 -> floor(8/1)=8 ; min=8.
        $this->assertSame(8, (int) $combo->variant->stock_quantity);
    }

    public function test_recargo_modo_mas_barata(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'cheap' => $cheap, 'pricey' => $pricey] = $this->sampleCombo('cheapest');
        $pricing = app(ComboPricing::class);
        $item = $combo->items->firstWhere('product_variant_id', null);

        $this->assertEqualsWithDelta(0.0, $pricing->surcharge($combo, $item, $cheap), 0.01);
        // 300 - 200 (la más barata) = 100.
        $this->assertEqualsWithDelta(100.0, $pricing->surcharge($combo, $item, $pricey), 0.01);
    }

    public function test_recargo_modo_hasta_un_precio(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'cheap' => $cheap, 'pricey' => $pricey] = $this->sampleCombo('up_to_price', basePrice: 250);
        $pricing = app(ComboPricing::class);
        $item = $combo->items->firstWhere('product_variant_id', null);

        // 200 <= 250 -> sin recargo ; 300 - 250 = 50.
        $this->assertEqualsWithDelta(0.0, $pricing->surcharge($combo, $item, $cheap), 0.01);
        $this->assertEqualsWithDelta(50.0, $pricing->surcharge($combo, $item, $pricey), 0.01);
    }

    public function test_recargo_modo_todas_al_mismo_precio(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'cheap' => $cheap, 'pricey' => $pricey] = $this->sampleCombo('all_same');
        $pricing = app(ComboPricing::class);
        $item = $combo->items->firstWhere('product_variant_id', null);

        $this->assertEqualsWithDelta(0.0, $pricing->surcharge($combo, $item, $cheap), 0.01);
        $this->assertEqualsWithDelta(0.0, $pricing->surcharge($combo, $item, $pricey), 0.01);
    }

    public function test_precio_total_con_seleccion(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'pricey' => $pricey] = $this->sampleCombo('cheapest', price: 250);
        $pricing = app(ComboPricing::class);
        $item = $combo->items->firstWhere('product_variant_id', null);

        // 250 + recargo(100 por la variante cara) = 350.
        $this->assertEqualsWithDelta(350.0, $pricing->priceForSelections($combo, [$item->id => $pricey]), 0.01);
    }

    public function test_venta_combo_derivado_descuenta_componentes(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'fixed' => $fixed, 'cheap' => $cheap, 'pricey' => $pricey] = $this->sampleCombo('cheapest', 'derived', 250);

        $sale = app(RegisterSale::class)->execute([[
            'variant_id' => $combo->product_variant_id,
            'quantity' => 1,
            'unit_price' => 350,
            'unit_cost' => (float) $combo->variant->cost,
            'combo_id' => $combo->id,
            'components' => [
                ['variant_id' => $fixed->id, 'quantity' => 1],
                ['variant_id' => $pricey->id, 'quantity' => 1],
            ],
        ]]);

        // Componentes descontados.
        $this->assertSame(9, (int) $fixed->fresh()->stock_quantity);
        $this->assertSame(7, (int) $pricey->fresh()->stock_quantity);
        $this->assertSame(5, (int) $cheap->fresh()->stock_quantity); // no elegida, intacta
        // La variante combo NO cambia su stock (derivado).
        $this->assertSame(8, (int) $combo->variant->fresh()->stock_quantity);
        // Una sola línea de venta, sobre la variante combo, al precio del combo.
        $this->assertSame(1, SaleItem::where('sale_id', $sale->id)->count());
        $this->assertSame($combo->product_variant_id, SaleItem::where('sale_id', $sale->id)->first()->product_variant_id);
        $this->assertEqualsWithDelta(350.0, (float) $sale->total, 0.01);
        // Movimientos de inventario solo de los componentes.
        $this->assertSame(2, InventoryMovement::where('source_id', $sale->id)->where('type', 'sale')->count());
    }

    public function test_venta_combo_derivado_sin_stock_falla(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'fixed' => $fixed, 'pricey' => $pricey] = $this->sampleCombo('cheapest', 'derived', 250);
        $fixed->update(['stock_quantity' => 0]);

        $this->expectException(ValidationException::class);

        app(RegisterSale::class)->execute([[
            'variant_id' => $combo->product_variant_id,
            'quantity' => 1,
            'unit_price' => 350,
            'combo_id' => $combo->id,
            'components' => [
                ['variant_id' => $fixed->id, 'quantity' => 1],
                ['variant_id' => $pricey->id, 'quantity' => 1],
            ],
        ]]);
    }

    public function test_venta_combo_propio_descuenta_su_stock(): void
    {
        $this->loginTenant();
        ['combo' => $combo, 'fixed' => $fixed, 'cheap' => $cheap] = $this->sampleCombo('all_same', 'own', 250);

        $sale = app(RegisterSale::class)->execute([[
            'variant_id' => $combo->product_variant_id,
            'quantity' => 2,
            'unit_price' => 250,
            'unit_cost' => (float) $combo->variant->cost,
        ]]);

        // Stock propio descontado; componentes intactos.
        $this->assertSame(18, (int) $combo->variant->fresh()->stock_quantity);
        $this->assertSame(10, (int) $fixed->fresh()->stock_quantity);
        $this->assertSame(5, (int) $cheap->fresh()->stock_quantity);
        $this->assertSame(1, InventoryMovement::where('source_id', $sale->id)->where('product_variant_id', $combo->product_variant_id)->count());
    }

    public function test_aislamiento_de_combos_por_comercio(): void
    {
        $this->loginTenant('A');
        $this->sampleCombo();
        $this->assertSame(1, Combo::count());

        $this->loginTenant('B');
        $this->assertSame(0, Combo::count());
    }

    public function test_producto_simple_se_fija_a_su_unica_variante(): void
    {
        $this->loginTenant();
        $simple = $this->variant(150, 70, 10); // producto simple con una variante

        $combo = app(ComboService::class)->create([
            'name' => 'Combo Simple',
            'pricing_mode' => 'all_same',
            'price' => 150,
            'stock_mode' => 'derived',
            'items' => [
                // Sin choose_at_sale ni variant_id: debe resolverse a la única variante.
                ['product_id' => $simple->product_id, 'choose_at_sale' => false, 'quantity' => 1],
            ],
        ]);

        $item = $combo->items->first();
        $this->assertSame($simple->id, $item->product_variant_id);
        $this->assertFalse($item->isChooseAtSale());
    }

    public function test_render_lista_de_combos(): void
    {
        $this->loginTenant();
        Filament::setCurrentPanel(Filament::getPanel('boutique'));
        $this->sampleCombo();

        Livewire::test(ListCombos::class)
            ->assertOk()
            ->assertSee('Combo Prueba');
    }

    public function test_crear_combo_por_el_asistente(): void
    {
        $this->loginTenant();
        Filament::setCurrentPanel(Filament::getPanel('boutique'));

        $fixed = $this->variant(100, 50, 10);
        $elegible = $this->variant(200, 100, 5, 'variable');

        Livewire::test(CreateCombo::class)
            ->fillForm([
                'items' => [
                    ['product_id' => $fixed->product_id, 'choose_at_sale' => false, 'product_variant_id' => $fixed->id, 'quantity' => 1],
                    ['product_id' => $elegible->product_id, 'choose_at_sale' => true, 'eligible_variant_ids' => [], 'quantity' => 1],
                ],
                'name' => 'Combo del asistente',
                'pricing_mode' => 'cheapest',
                'price' => 250,
                'stock_mode' => 'derived',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $combo = Combo::query()->where('name', 'Combo del asistente')->first();
        $this->assertNotNull($combo);
        $this->assertSame('combo', $combo->product->type);
        $this->assertCount(2, $combo->items);
        $this->assertEqualsWithDelta(250.0, (float) $combo->price, 0.01);
    }

    public function test_pos_vende_combo_derivado_eligiendo_variante(): void
    {
        $this->loginTenant();
        Filament::setCurrentPanel(Filament::getPanel('boutique'));
        ['combo' => $combo, 'fixed' => $fixed, 'pricey' => $pricey] = $this->sampleCombo('cheapest', 'derived', 250);
        $chooseItem = $combo->items->firstWhere('product_variant_id', null);

        $component = Livewire::test(Pos::class)
            ->call('addToCart', $combo->product_variant_id);

        // El combo tiene componente "a elegir": se abre el selector.
        $this->assertSame($combo->id, $component->get('comboSelecting'));

        $component
            ->set('comboSelections.'.$chooseItem->id, $pricey->id)
            ->call('confirmCombo');

        $cart = $component->get('cart');
        $this->assertCount(1, $cart);
        $line = array_values($cart)[0];
        // 250 + recargo 100 (variante cara) = 350.
        $this->assertEqualsWithDelta(350.0, $line['price'], 0.01);

        $component->set('amountPaid', 350)->call('register')->assertHasNoErrors();

        $this->assertSame(9, (int) $fixed->fresh()->stock_quantity);
        $this->assertSame(7, (int) $pricey->fresh()->stock_quantity);
        $this->assertDatabaseHas('sales', ['total' => 350]);
    }

    public function test_pos_busca_combos_sin_distinguir_mayusculas(): void
    {
        $this->loginTenant();
        Filament::setCurrentPanel(Filament::getPanel('boutique'));
        $this->sampleCombo();

        // En minúsculas: debe encontrar el combo (y no fallar en SQLite por ilike).
        Livewire::test(Pos::class)
            ->set('search', 'combo prueba')
            ->assertHasNoErrors()
            ->assertSee('Combo Prueba');
    }

    public function test_pos_vende_combo_propio_directo(): void
    {
        $this->loginTenant();
        Filament::setCurrentPanel(Filament::getPanel('boutique'));
        // Combo de stock propio sin componentes "a elegir".
        $fixedA = $this->variant(100, 50, 10);
        $fixedB = $this->variant(200, 100, 10);
        $combo = app(ComboService::class)->create([
            'name' => 'Combo Propio',
            'pricing_mode' => 'all_same',
            'price' => 250,
            'stock_mode' => 'own',
            'stock_quantity' => 20,
            'items' => [
                ['product_id' => $fixedA->product_id, 'choose_at_sale' => false, 'product_variant_id' => $fixedA->id, 'quantity' => 1],
                ['product_id' => $fixedB->product_id, 'choose_at_sale' => false, 'product_variant_id' => $fixedB->id, 'quantity' => 1],
            ],
        ]);

        $component = Livewire::test(Pos::class)
            ->call('addToCart', $combo->product_variant_id);

        // Sin "a elegir": no abre selector, entra directo al carrito.
        $this->assertNull($component->get('comboSelecting'));
        $this->assertCount(1, $component->get('cart'));

        $component->set('amountPaid', 250)->call('register')->assertHasNoErrors();

        $this->assertSame(19, (int) $combo->variant->fresh()->stock_quantity);
        $this->assertSame(10, (int) $fixedA->fresh()->stock_quantity);
    }
}
