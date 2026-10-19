<?php

namespace Tests\Unit;

use App\Support\Barcode;
use PHPUnit\Framework\TestCase;

class BarcodeTest extends TestCase
{
    public function test_genera_un_ean13_valido_y_aleatorio(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = Barcode::generateEan13();

            $this->assertMatchesRegularExpression('/^\d{13}$/', $code, 'Debe tener 13 dígitos');
            $this->assertTrue(Barcode::isValidEan13($code), "El código $code debe ser un EAN-13 válido");
            $this->assertSame('2', $code[0], 'Debe usar el prefijo interno 2x');
        }
    }

    public function test_digito_verificador_contra_un_ean13_conocido(): void
    {
        // 4006381333931 es un EAN-13 válido de ejemplo.
        $this->assertSame('1', Barcode::ean13CheckDigit('400638133393'));
        $this->assertTrue(Barcode::isValidEan13('4006381333931'));
        $this->assertFalse(Barcode::isValidEan13('4006381333930'));
        $this->assertFalse(Barcode::isValidEan13('123'));
    }
}
