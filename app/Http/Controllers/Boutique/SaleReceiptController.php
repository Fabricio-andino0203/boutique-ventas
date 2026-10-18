<?php

namespace App\Http\Controllers\Boutique;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Support\CurrentTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Muestra el recibo imprimible de una venta.
 *
 * Exige sesión del panel de boutique y aísla por comercio: la venta llega
 * resuelta por binding implícito, que aplica el TenantScope, de modo que una
 * venta de otro comercio nunca se encuentra y Laravel responde 404. La
 * verificación explícita contra el comercio activo refuerza ese aislamiento.
 */
class SaleReceiptController extends Controller
{
    public function __invoke(Sale $sale, CurrentTenant $currentTenant): View|RedirectResponse
    {
        if (Auth::guest()) {
            return redirect()->guest(route('filament.boutique.auth.login'));
        }

        abort_unless($sale->tenant_id === $currentTenant->id(), 404);

        $sale->load(['items.variant.product', 'customer', 'user']);

        return view('filament.boutique.receipt', [
            'sale' => $sale,
            'tenant' => $currentTenant->get(),
        ]);
    }
}
