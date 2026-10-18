<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Evita el 403 sin salida cuando un usuario autenticado abre un panel que no le
 * corresponde (p. ej. un usuario de boutique entrando a /admin): lo redirige al
 * panel que sí puede usar. Si no puede usar ninguno, deja que Filament resuelva.
 */
class RedirectToAccessiblePanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();
        $user = Auth::user();

        if ($panel !== null && $user !== null && ! $user->canAccessPanel($panel)) {
            foreach (Filament::getPanels() as $candidate) {
                if ($candidate->getId() === $panel->getId()) {
                    continue;
                }

                if ($user->canAccessPanel($candidate)) {
                    return redirect()->to($candidate->getUrl());
                }
            }
        }

        return $next($request);
    }
}
