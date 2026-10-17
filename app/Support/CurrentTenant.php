<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Mantiene el comercio (tenant) activo durante el ciclo de vida del request.
 * Registrado como singleton en el contenedor para resolver el aislamiento de datos.
 */
class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
