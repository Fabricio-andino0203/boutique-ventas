<?php

use App\Http\Controllers\Boutique\BarcodeLabelsController;
use App\Http\Controllers\Boutique\SaleReceiptController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * Recibo imprimible de una venta. Usa la sesión web del panel de boutique; el
 * controlador exige autenticación (redirige al login si no la hay) y el binding
 * de la venta aplica el aislamiento por comercio vía TenantScope.
 */
Route::middleware('web')
    ->get('/tienda/ventas/{sale}/recibo', SaleReceiptController::class)
    ->name('boutique.sales.receipt');

/*
 * Vista imprimible de etiquetas con código de barras. Usa la sesión web del panel
 * de boutique (lee la selección guardada por la página de Códigos de barras).
 */
Route::middleware('web')
    ->get('/tienda/etiquetas/imprimir', BarcodeLabelsController::class)
    ->name('boutique.barcodes.print');
