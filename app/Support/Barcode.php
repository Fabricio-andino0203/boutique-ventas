<?php

namespace App\Support;

/**
 * Generación y validación de códigos de barras EAN-13 (el estándar que leen
 * los escáneres de supermercado/retail). Un EAN-13 son 13 dígitos: 12 de datos
 * + 1 dígito verificador calculado, por lo que un número aleatorio "a secas" no
 * sirve: debe llevar el dígito de control correcto.
 */
class Barcode
{
    /**
     * Genera un EAN-13 válido y aleatorio.
     *
     * Usa el prefijo 2 (rango 20–29), reservado por el estándar para uso
     * interno/en tienda, así no colisiona con códigos de fabricantes reales.
     */
    public static function generateEan13(): string
    {
        $digits = '2';
        for ($i = 0; $i < 11; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        // $digits tiene 12 dígitos de datos.
        return $digits.self::ean13CheckDigit($digits);
    }

    /**
     * Calcula el dígito verificador EAN-13 de 12 dígitos de datos.
     * Pesos alternados 1 y 3 (de izquierda a derecha, 1-indexado).
     */
    public static function ean13CheckDigit(string $twelveDigits): string
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $twelveDigits[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }

        return (string) ((10 - ($sum % 10)) % 10);
    }

    /**
     * Indica si un código es un EAN-13 válido (13 dígitos y verificador correcto).
     */
    public static function isValidEan13(string $code): bool
    {
        if (! preg_match('/^\d{13}$/', $code)) {
            return false;
        }

        return self::ean13CheckDigit(substr($code, 0, 12)) === $code[12];
    }
}
