<?php

use Filament\Facades\Filament;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway (y otros PaaS) terminan TLS en un proxy; confiamos en él para
        // que las URLs y assets se generen con el esquema correcto (HTTPS).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Evita un 403 sin salida cuando un usuario autenticado abre un panel que
        // no le corresponde (p. ej. un usuario de boutique entrando a /admin):
        // lo redirige al panel que sí puede usar.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403 || $request->expectsJson()) {
                return null;
            }

            $user = Auth::user();

            if ($user === null) {
                return null;
            }

            foreach (Filament::getPanels() as $panel) {
                $path = trim($panel->getPath(), '/');

                if ($path === '' || ! $request->is($path, $path.'/*')) {
                    continue;
                }

                if ($user->canAccessPanel($panel)) {
                    continue;
                }

                foreach (Filament::getPanels() as $candidate) {
                    if ($candidate->getId() !== $panel->getId() && $user->canAccessPanel($candidate)) {
                        return redirect()->to($candidate->getUrl());
                    }
                }
            }

            // Usuario de boutique cuyo comercio no tiene suscripción vigente: no hay
            // otro panel al que redirigirlo, así que en vez de un 403 sin salida le
            // mostramos un mensaje claro para que contacte al administrador.
            if (! $user->is_super_admin && $user->tenant?->hasActiveSubscription() === false) {
                return response()->view('errors.subscription-inactive', status: 403);
            }

            return null;
        });
    })->create();
