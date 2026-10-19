<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Support\Barcode;

/**
 * Garantiza que una variante tenga un código de barras EAN-13 válido y único,
 * de modo que la etiqueta impresa coincida con lo que el escáner buscará en el POS.
 */
class VariantBarcode
{
    /** Devuelve el EAN-13 de la variante, generándolo y guardándolo si falta o es inválido. */
    public function ensure(ProductVariant $variant): string
    {
        if (filled($variant->barcode) && Barcode::isValidEan13($variant->barcode)) {
            return $variant->barcode;
        }

        $code = $this->generateUnique();
        $variant->barcode = $code;
        $variant->save();

        return $code;
    }

    /** Genera un EAN-13 que no colisione con otro código de barras ya registrado. */
    public function generateUnique(): string
    {
        do {
            $code = Barcode::generateEan13();
        } while (ProductVariant::query()->where('barcode', $code)->exists());

        return $code;
    }
}
