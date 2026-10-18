<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $name = 'Comercio'): Tenant
    {
        return Tenant::create(['name' => $name, 'slug' => strtolower($name).'-'.uniqid()]);
    }

    private function plan(): Plan
    {
        return Plan::create([
            'name' => 'Mensual',
            'slug' => 'mensual-'.uniqid(),
            'price' => 500,
            'duration_days' => 30,
        ]);
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

    private function subscription(Tenant $tenant, string $status, ?string $endsAt): Subscription
    {
        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $this->plan()->id,
            'status' => $status,
            'starts_at' => now()->subDays(5),
            'ends_at' => $endsAt,
            'price' => 500,
            'currency' => 'HNL',
        ]);
    }

    public function test_boutique_con_suscripcion_activa_accede_al_panel(): void
    {
        $tenant = $this->tenant();
        $this->subscription($tenant, 'active', now()->addDays(25)->toDateTimeString());
        $user = $this->boutiqueUser($tenant);

        $this->assertTrue($tenant->hasActiveSubscription());
        $this->assertTrue($user->canAccessPanel(Filament::getPanel('boutique')));

        $this->actingAs($user)->get('/tienda')->assertOk();
    }

    public function test_boutique_con_suscripcion_vencida_no_accede_y_ve_mensaje(): void
    {
        $tenant = $this->tenant();
        $this->subscription($tenant, 'active', now()->subDay()->toDateTimeString());
        $user = $this->boutiqueUser($tenant);

        $this->assertFalse($tenant->hasActiveSubscription());
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('boutique')));

        $response = $this->actingAs($user)->get('/tienda');

        $response->assertForbidden();
        $response->assertSee('Tu suscripción venció o está inactiva. Contactá al administrador para reactivar tu cuenta.');
    }

    public function test_boutique_con_suscripcion_suspendida_no_accede(): void
    {
        $tenant = $this->tenant();
        $this->subscription($tenant, 'suspended', now()->addDays(25)->toDateTimeString());
        $user = $this->boutiqueUser($tenant);

        $this->assertFalse($tenant->hasActiveSubscription());
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('boutique')));
    }

    public function test_boutique_sin_suscripcion_no_accede(): void
    {
        $tenant = $this->tenant();
        $user = $this->boutiqueUser($tenant);

        $this->assertFalse($tenant->hasActiveSubscription());
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('boutique')));
    }

    public function test_suscripcion_sin_vencimiento_cuenta_como_activa(): void
    {
        $tenant = $this->tenant();
        $this->subscription($tenant, 'active', null);

        $this->assertTrue($tenant->hasActiveSubscription());
    }

    public function test_super_admin_siempre_accede_a_admin(): void
    {
        $admin = User::create([
            'name' => 'Dueño', 'email' => 'admin@test.local', 'password' => 'secret',
            'is_super_admin' => true, 'is_active' => true, 'tenant_id' => null,
        ]);

        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->assertFalse($admin->canAccessPanel(Filament::getPanel('boutique')));
    }

    public function test_suscripcion_de_otro_comercio_no_habilita_el_acceso(): void
    {
        $tenantA = $this->tenant('A');
        $tenantB = $this->tenant('B');
        $this->subscription($tenantB, 'active', now()->addDays(25)->toDateTimeString());

        $userA = $this->boutiqueUser($tenantA);

        $this->assertFalse($tenantA->hasActiveSubscription());
        $this->assertFalse($userA->canAccessPanel(Filament::getPanel('boutique')));
    }
}
