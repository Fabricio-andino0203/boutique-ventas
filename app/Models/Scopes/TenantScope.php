<?php

namespace App\Models\Scopes;

use App\Support\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restringe toda consulta de un modelo de boutique al comercio activo.
 * Si no hay comercio activo en el contexto (p. ej. backoffice), no aplica filtro;
 * la seguridad se refuerza además con RLS en PostgreSQL.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentTenant = app(CurrentTenant::class);

        if ($currentTenant->check()) {
            $builder->where($model->getTable().'.tenant_id', $currentTenant->id());
        }
    }
}
