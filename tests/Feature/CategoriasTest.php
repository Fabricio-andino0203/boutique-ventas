<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class CategoriasTest extends TestCase
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

    public function test_crear_categoria_asigna_comercio_y_genera_slug(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $cat = Category::create(['name' => 'Vestidos', 'description' => 'Prendas de vestir']);

        $this->assertSame($tenant->id, $cat->tenant_id);
        $this->assertSame('vestidos', $cat->slug);
        $this->assertNull($cat->parent_id);
        $this->assertSame('Prendas de vestir', $cat->description);
    }

    public function test_subcategoria_hereda_comercio_y_queda_bajo_la_madre(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $madre = Category::create(['name' => 'Vestidos']);
        $sub = $madre->children()->create(['name' => 'Largos']);

        $this->assertSame($madre->id, $sub->parent_id);
        $this->assertSame($tenant->id, $sub->tenant_id);
        $this->assertSame(1, $madre->children()->count());
    }

    public function test_el_scope_aisla_categorias_por_comercio(): void
    {
        $a = $this->tenant('A');
        $b = $this->tenant('B');
        Category::create(['name' => 'Cat A', 'tenant_id' => $a->id]);
        Category::create(['name' => 'Cat B', 'tenant_id' => $b->id]);

        Auth::login($this->boutiqueUser($a));

        $this->assertSame(1, Category::count());
        $this->assertSame('Cat A', Category::first()->name);
    }

    public function test_descripcion_e_imagen_son_opcionales(): void
    {
        $tenant = $this->tenant();
        Auth::login($this->boutiqueUser($tenant));

        $cat = Category::create(['name' => 'Simple']);

        $this->assertNull($cat->description);
        $this->assertNull($cat->image_path);
    }
}
