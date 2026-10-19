<?php

namespace Tests\Feature;

use App\Filament\Boutique\Pages\ProductImages;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductImageAssigner;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class ImagenesProductosTest extends TestCase
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

    private function productoVariable(): Product
    {
        $product = Product::create(['name' => 'Blusa', 'type' => 'variable', 'default_price' => 300]);
        $product->variants()->create(['price' => 300, 'stock_quantity' => 2, 'options' => ['Color' => 'Rojo', 'Talla' => 'S']]);
        $product->variants()->create(['price' => 300, 'stock_quantity' => 2, 'options' => ['Color' => 'Rojo', 'Talla' => 'M']]);
        $product->variants()->create(['price' => 300, 'stock_quantity' => 2, 'options' => ['Color' => 'Azul', 'Talla' => 'S']]);

        return $product;
    }

    public function test_imagen_principal_se_propaga_a_variantes_sin_imagen(): void
    {
        Auth::login($this->boutiqueUser($this->tenant()));
        $product = $this->productoVariable();
        // Una variante ya trae imagen propia: no debe sobrescribirse.
        $product->variants()->first()->update(['image_path' => 'products/propia.jpg']);

        app(ProductImageAssigner::class)->assignMain($product, 'products/principal.jpg', true);

        $this->assertSame('products/principal.jpg', $product->fresh()->image_path);
        $this->assertSame('products/propia.jpg', $product->variants()->first()->image_path);
        $this->assertSame(2, $product->variants()->where('image_path', 'products/principal.jpg')->count());
    }

    public function test_imagen_por_valor_de_opcion_cubre_las_variantes_que_lo_comparten(): void
    {
        Auth::login($this->boutiqueUser($this->tenant()));
        $product = $this->productoVariable();

        $updated = app(ProductImageAssigner::class)->assignByOptionValue($product, 'Color', 'Rojo', 'products/rojo.jpg');

        $this->assertSame(2, $updated);
        $this->assertSame(2, $product->variants()->where('image_path', 'products/rojo.jpg')->count());
        $azul = $product->variants()->get()->firstWhere(fn ($v) => ($v->options['Color'] ?? null) === 'Azul');
        $this->assertNull($azul->image_path);
    }

    public function test_option_values_agrupa_valores_distintos(): void
    {
        Auth::login($this->boutiqueUser($this->tenant()));
        $product = $this->productoVariable();

        $values = app(ProductImageAssigner::class)->optionValues($product);

        $this->assertEqualsCanonicalizing(['Rojo', 'Azul'], $values['Color']);
        $this->assertEqualsCanonicalizing(['S', 'M'], $values['Talla']);
    }

    public function test_la_pagina_lista_productos_y_filtra_sin_imagen(): void
    {
        Auth::login($this->boutiqueUser($this->tenant()));
        Filament::setCurrentPanel(Filament::getPanel('boutique'));

        $conImagen = Product::create(['name' => 'Con Foto', 'default_price' => 100, 'image_path' => 'products/x.jpg']);
        $conImagen->variants()->create(['price' => 100, 'stock_quantity' => 1]);
        $sinImagen = Product::create(['name' => 'Sin Foto', 'default_price' => 100]);
        $sinImagen->variants()->create(['price' => 100, 'stock_quantity' => 1]);

        Livewire::test(ProductImages::class)
            ->assertSee('Con Foto')
            ->assertSee('Sin Foto')
            ->set('onlyWithoutImage', true)
            ->assertSee('Sin Foto')
            ->assertDontSee('Con Foto');
    }

    public function test_la_accion_de_imagen_se_monta_y_precarga_el_producto(): void
    {
        Auth::login($this->boutiqueUser($this->tenant()));
        Filament::setCurrentPanel(Filament::getPanel('boutique'));
        $product = $this->productoVariable();
        $product->update(['image_path' => 'products/actual.jpg']);

        Livewire::test(ProductImages::class)
            ->mountAction('asignarImagen', ['product' => $product->id])
            ->assertActionMounted('asignarImagen')
            ->assertSchemaStateSet([
                'propagate' => false,
            ]);
    }

    public function test_has_any_image_detecta_imagen_en_producto_o_variante(): void
    {
        Auth::login($this->boutiqueUser($this->tenant()));
        $assigner = app(ProductImageAssigner::class);

        $simple = Product::create(['name' => 'Gorra', 'default_price' => 100]);
        $simple->variants()->create(['price' => 100, 'stock_quantity' => 1]);
        $this->assertFalse($assigner->hasAnyImage($simple));

        $simple->variants()->first()->update(['image_path' => 'products/g.jpg']);
        $this->assertTrue($assigner->hasAnyImage($simple->fresh()));
    }
}
