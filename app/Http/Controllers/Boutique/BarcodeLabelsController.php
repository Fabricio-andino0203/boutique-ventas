<?php

namespace App\Http\Controllers\Boutique;

use App\Http\Controllers\Controller;
use App\Support\Barcode;
use App\Support\CurrentTenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Vista imprimible de etiquetas con código de barras EAN-13. Lee la selección y
 * las opciones que dejó la página de Códigos de barras en la sesión. Exige sesión
 * del panel de boutique.
 */
class BarcodeLabelsController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant): View|RedirectResponse
    {
        if (Auth::guest()) {
            return redirect()->guest(route('filament.boutique.auth.login'));
        }

        $payload = session('barcode_labels');
        if (! is_array($payload) || empty($payload['items'])) {
            return redirect()->route('filament.boutique.pages.barcodes');
        }

        $options = $payload['options'] ?? [];

        // Expande cada ítem a sus N etiquetas y precalcula el SVG del EAN-13.
        $labels = [];
        foreach ($payload['items'] as $item) {
            if (! Barcode::isValidEan13($item['code'] ?? '')) {
                continue;
            }
            $svg = Barcode::ean13Svg($item['code'], moduleWidth: 1.6, barHeight: 42, showText: false);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            for ($i = 0; $i < $quantity; $i++) {
                $labels[] = [
                    'name' => $item['name'] ?? '',
                    'code' => $item['code'],
                    'price' => (float) ($item['price'] ?? 0),
                    'svg' => $svg,
                ];
            }
        }

        return view('filament.boutique.labels', [
            'labels' => $labels,
            'options' => $options,
            'tenant' => $currentTenant->get(),
        ]);
    }
}
