<?php

namespace App\Http\Middleware;

use App\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fija el comercio (tenant) activo a partir del usuario autenticado, de modo que
 * el TenantScope filtre automáticamente todos los datos del panel de boutique.
 */
class SetCurrentTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user !== null && $user->tenant !== null) {
            $this->currentTenant->set($user->tenant);
        }

        return $next($request);
    }
}
