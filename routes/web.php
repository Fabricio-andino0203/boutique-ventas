<?php

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
