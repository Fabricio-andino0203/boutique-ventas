<?php

namespace Tests\Feature;

use App\Filament\Boutique\Resources\Products\Schemas\ProductForm;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProductosTest extends TestCase
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

    public function test_producto_guarda_los_campos_de_catalogo(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));
        $cat = Category::create(['name' => 'Vestidos']);
        $sub = $cat->children()->create(['name' => 'Largos']);
        $supplier = Supplier::create(['name' => 'Proveedor X']);

        $product = Product::create([
            'type' => 'variable',
            'name' => 'Vestido',
            'category_id' => $cat->id,
            'subcategory_id' => $sub->id,
            'supplier_id' => $supplier->id,
            'code' => 'P-1',
            'barcode' => '123',
            'unit' => 'Unidad',
            'default_price' => 500,
            'default_cost' => 200,
            'wholesale_price' => 400,
            'min_stock' => 2,
            'allow_discount' => false,
            'tax_type' => 'additional',
            'tax_rate' => 15,
        ]);

        $this->assertSame($tenant->id, $product->tenant_id);
        $this->assertSame('variable', $product->type);
        $this->assertSame($sub->id, $product->subcategory->id);
        $this->assertSame($supplier->id, $product->supplier->id);
        $this->assertSame('additional', $product->tax_type);
        $this->assertFalse($product->allow_discount);
    }

    public function test_producto_simple_crea_una_variante_con_los_datos(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $product = Product::create([
            'type' => 'simple',
            'name' => 'Blusa',
            'default_price' => 500,
            'default_cost' => 200,
            'min_stock' => 1,
            'code' => 'B-1',
            'barcode' => '999',
        ]);

        ProductForm::syncSimpleVariant($product, [
            'default_price' => 500,
            'default_cost' => 200,
            'wholesale_price' => 400,
            'simple_stock' => 10,
            'min_stock' => 1,
            'code' => 'B-1',
            'barcode' => '999',
        ]);

        $variant = $product->variants()->first();

        $this->assertNotNull($variant);
        $this->assertEquals(500, (float) $variant->price);
        $this->assertEquals(200, (float) $variant->cost);
        $this->assertSame(10, $variant->stock_quantity);
        $this->assertSame($tenant->id, $variant->tenant_id);
        $this->assertSame('B-1', $variant->sku);
        $this->assertSame('999', $variant->barcode);
    }

    public function test_aislamiento_de_productos_por_comercio(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        Product::create(['name' => 'A1', 'tenant_id' => $a->id]);
        Product::create(['name' => 'B1', 'tenant_id' => $b->id]);

        Auth::login($this->boutiqueUser($a));

        $this->assertSame(1, Product::count());
        $this->assertSame('A1', Product::first()->name);
    }
}
