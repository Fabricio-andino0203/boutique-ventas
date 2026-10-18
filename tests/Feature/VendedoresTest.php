<?php

namespace Tests\Feature;

use App\Filament\Boutique\Resources\Users\Pages\CreateUser;
use App\Filament\Boutique\Resources\Users\Pages\EditUser;
use App\Filament\Boutique\Resources\Users\Pages\ListUsers;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class VendedoresTest extends TestCase
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
            'name' => 'Dueño',
            'email' => 'u'.uniqid().'@test.local',
            'password' => 'secret',
            'is_super_admin' => false,
            'is_active' => true,
        ]);
    }

    private function actuarComo(User $user): void
    {
        Auth::login($user);
        Filament::setCurrentPanel(Filament::getPanel('boutique'));
    }

    public function test_crear_vendedor_lo_asigna_al_comercio_y_hashea_la_contrasena(): void
    {
        $tenant = $this->tenant();
        $this->actuarComo($this->boutiqueUser($tenant));

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Ana Vendedora',
                'email' => 'ana@test.local',
                'password' => 'clave-secreta',
                'role' => 'cashier',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $vendedor = User::where('email', 'ana@test.local')->first();

        $this->assertNotNull($vendedor);
        $this->assertSame($tenant->id, $vendedor->tenant_id);
        $this->assertFalse($vendedor->is_super_admin);
        $this->assertTrue($vendedor->is_active);
        $this->assertNotSame('clave-secreta', $vendedor->password);
        $this->assertTrue(Hash::check('clave-secreta', $vendedor->password));
    }

    public function test_la_lista_solo_muestra_vendedores_del_comercio_actual(): void
    {
        $tenantA = $this->tenant('A');
        $tenantB = $this->tenant('B');

        $propioA = $this->boutiqueUser($tenantA);
        $ajenoB = $this->boutiqueUser($tenantB);

        $superAdmin = User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Super',
            'email' => 'super@test.local',
            'password' => 'secret',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        $this->actuarComo($propioA);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$propioA])
            ->assertCanNotSeeTableRecords([$ajenoB, $superAdmin]);
    }

    public function test_editar_sin_contrasena_conserva_la_actual(): void
    {
        $tenant = $this->tenant();
        $propio = $this->boutiqueUser($tenant);
        $vendedor = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Luis',
            'email' => 'luis@test.local',
            'password' => 'original',
            'role' => 'cashier',
            'is_super_admin' => false,
            'is_active' => true,
        ]);
        $hashOriginal = $vendedor->password;

        $this->actuarComo($propio);

        Livewire::test(EditUser::class, [
            'record' => $vendedor->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Luis Editado',
                'password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $vendedor->refresh();

        $this->assertSame('Luis Editado', $vendedor->name);
        $this->assertSame($hashOriginal, $vendedor->password);
    }
}
