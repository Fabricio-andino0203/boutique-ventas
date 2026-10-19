<?php

namespace Tests\Unit;

use App\Support\Barcode;
use PHPUnit\Framework\TestCase;

class BarcodeTest extends TestCase
{
    public function test_genera_un_ean13_valido(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $code = Barcode::generateEan13();
            $this->assertMatchesRegularExpression('/^\d{13}$/', $code);
            $this->assertTrue(Barcode::isValidEan13($code));
            $this->assertSame('2', $code[0]);
        }
    }

    public function test_rechaza_codigos_invalidos(): void
    {
        $this->assertFalse(Barcode::isValidEan13('123'));
        $this->assertFalse(Barcode::isValidEan13('5901234123450')); // verificador incorrecto
        $this->assertTrue(Barcode::isValidEan13('5901234123457')); // ejemplo estándar válido
    }

    public function test_el_patron_tiene_95_modulos_con_guardas(): void
    {
        $pattern = Barcode::ean13Pattern('5901234123457');

        $this->assertSame(95, strlen($pattern));
        $this->assertSame('101', substr($pattern, 0, 3));
        $this->assertSame('01010', substr($pattern, 45, 5));
        $this->assertSame('101', substr($pattern, 92, 3));
    }

    public function test_el_patron_se_decodifica_al_codigo_original(): void
    {
        $code = '5901234123457';
        $pattern = Barcode::ean13Pattern($code);

        $this->assertSame($code, $this->decode($pattern));
    }

    public function test_el_svg_incluye_las_barras_y_el_numero(): void
    {
        $svg = Barcode::ean13Svg('5901234123457');

        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<rect', $svg);
        $this->assertStringContainsString('901234', $svg);
        $this->assertStringContainsString('123457', $svg);
    }

    public function test_el_svg_sin_texto_no_incluye_numeros(): void
    {
        $svg = Barcode::ean13Svg('5901234123457', showText: false);

        $this->assertStringNotContainsString('<text', $svg);
    }

    /** Decodifica el patrón de 95 módulos a los 13 dígitos (verifica la codificación). */
    private function decode(string $pattern): string
    {
        $l = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
        $g = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
        $r = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
        $parityToFirst = [
            'LLLLLL' => 0, 'LLGLGG' => 1, 'LLGGLG' => 2, 'LLGGGL' => 3, 'LGLLGG' => 4,
            'LGGLLG' => 5, 'LGGGLL' => 6, 'LGLGLG' => 7, 'LGLGGL' => 8, 'LGGLGL' => 9,
        ];

        $left = substr($pattern, 3, 42);
        $right = substr($pattern, 50, 42);

        $parity = '';
        $digits = '';
        for ($i = 0; $i < 6; $i++) {
            $chunk = substr($left, $i * 7, 7);
            $lIndex = array_search($chunk, $l, true);
            if ($lIndex !== false) {
                $parity .= 'L';
                $digits .= (string) $lIndex;
            } else {
                $parity .= 'G';
                $digits .= (string) array_search($chunk, $g, true);
            }
        }

        $rightDigits = '';
        for ($i = 0; $i < 6; $i++) {
            $chunk = substr($right, $i * 7, 7);
            $rightDigits .= (string) array_search($chunk, $r, true);
        }

        return $parityToFirst[$parity].$digits.$rightDigits;
    }
}
