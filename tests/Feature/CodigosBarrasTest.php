<?php

namespace Tests\Feature;

use App\Filament\Boutique\Pages\Barcodes;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\VariantBarcode;
use App\Support\Barcode;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class CodigosBarrasTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create(['name' => 'Comercio', 'slug' => 'c-'.uniqid()]);
    }

    private function boutiqueUser(Tenant $tenant): User
    {
        return User::create([
            'tenant_id' => $tenant->id, 'name' => 'V', 'email' => 'u'.uniqid().'@t.local',
            'password' => 'secret', 'is_super_admin' => false, 'is_active' => true,
        ]);
    }

    private function login(): void
    {
        Auth::login($this->boutiqueUser($this->tenant()));
        Filament::setCurrentPanel(Filament::getPanel('boutique'));
    }

    private function variant(int $stock = 5, float $price = 300, ?string $barcode = null): ProductVariant
    {
        $product = Product::create(['name' => 'Vestido', 'default_price' => $price]);

        return $product->variants()->create([
            'price' => $price, 'stock_quantity' => $stock, 'barcode' => $barcode,
        ]);
    }

    public function test_ensure_asigna_un_ean13_valido_si_falta(): void
    {
        $this->login();
        $variant = $this->variant(barcode: null);

        $code = app(VariantBarcode::class)->ensure($variant);

        $this->assertTrue(Barcode::isValidEan13($code));
        $this->assertSame($code, $variant->fresh()->barcode);
    }

    public function test_ensure_conserva_un_ean13_valido_existente(): void
    {
        $this->login();
        $existing = Barcode::generateEan13();
        $variant = $this->variant(barcode: $existing);

        $code = app(VariantBarcode::class)->ensure($variant);

        $this->assertSame($existing, $code);
    }

    public function test_agregar_variante_la_pone_en_la_seleccion_con_codigo(): void
    {
        $this->login();
        $variant = $this->variant();

        Livewire::test(Barcodes::class)
            ->call('addVariant', $variant->id)
            ->assertSet('totalLabels', 1);

        $this->assertTrue(Barcode::isValidEan13($variant->fresh()->barcode));
    }

    public function test_segun_stock_pone_una_etiqueta_por_unidad(): void
    {
        $this->login();
        $variant = $this->variant(stock: 7);

        $component = Livewire::test(Barcodes::class)
            ->call('addVariant', $variant->id)
            ->call('segunStock')
            ->assertSet('totalLabels', 7);

        $this->assertSame(7, $component->get('selected')[$variant->id]['quantity']);
    }

    public function test_incrementar_respeta_el_maximo(): void
    {
        $this->login();
        $variant = $this->variant(stock: 999);

        $component = Livewire::test(Barcodes::class)
            ->call('addVariant', $variant->id)
            ->call('segunStock'); // 999 -> recortado a 300

        $this->assertSame(Barcodes::MAX_LABELS, $component->get('totalLabels'));
    }

    public function test_escanear_codigo_agrega_la_variante(): void
    {
        $this->login();
        $code = Barcode::generateEan13();
        $variant = $this->variant(barcode: $code);

        Livewire::test(Barcodes::class)
            ->set('search', $code)
            ->assertSet('totalLabels', 1)
            ->assertSet('search', '');
    }

    public function test_imprimir_guarda_en_sesion_y_redirige(): void
    {
        $this->login();
        $variant = $this->variant();

        Livewire::test(Barcodes::class)
            ->call('addVariant', $variant->id)
            ->call('imprimir')
            ->assertRedirect(route('boutique.barcodes.print'));

        $payload = session('barcode_labels');
        $this->assertNotEmpty($payload['items']);
        $this->assertSame($variant->fresh()->barcode, $payload['items'][0]['code']);
    }

    public function test_la_vista_de_impresion_renderiza_las_etiquetas(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);
        Auth::login($user);
        $variant = $this->variant();
        $code = app(VariantBarcode::class)->ensure($variant);

        session()->put('barcode_labels', [
            'items' => [[
                'variant_id' => $variant->id, 'name' => 'Vestido', 'code' => $code,
                'price' => 300.0, 'stock' => 5, 'quantity' => 2,
            ]],
            'options' => ['showName' => true, 'showPrice' => true, 'belowBars' => 'code', 'printer' => 'normal', 'labelWidth' => 50, 'labelHeight' => 25, 'perRow' => 1],
        ]);

        $response = $this->actingAs($user)->get(route('boutique.barcodes.print'));

        $response->assertOk();
        $response->assertSee('Vestido');
        $response->assertSee($code);
        $response->assertSee('L 300.00');
        // 2 etiquetas => 2 SVG de barras.
        $this->assertSame(2, substr_count($response->getContent(), '<svg'));
    }
}
