<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve el comercio (tenant) activo del request.
 *
 * Por defecto lo deriva del usuario autenticado (funciona tanto en cargas de
 * página como en peticiones AJAX de Livewire). Puede fijarse explícitamente con
 * set() para contextos sin sesión (comandos de consola, colas, pruebas).
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    private bool $explicitlySet = false;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
        $this->explicitlySet = true;
    }

    public function get(): ?Tenant
    {
        if ($this->explicitlySet) {
            return $this->tenant;
        }

        return Auth::user()?->tenant;
    }

    public function id(): ?int
    {
        return $this->get()?->id;
    }

    public function check(): bool
    {
        return $this->id() !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
        $this->explicitlySet = false;
    }
}
