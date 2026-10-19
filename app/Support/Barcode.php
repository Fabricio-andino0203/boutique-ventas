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

    /** Codificación L (izquierda, paridad impar) por dígito. */
    private const L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];

    /** Codificación G (izquierda, paridad par) por dígito. */
    private const G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];

    /** Codificación R (derecha) por dígito. */
    private const R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];

    /** Patrón de paridad L/G de los 6 dígitos izquierdos según el primer dígito. */
    private const PARITY = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'];

    /**
     * Devuelve la cadena de 95 módulos (0/1) que representa el EAN-13.
     * Guardas: inicio 101, centro 01010, fin 101.
     */
    public static function ean13Pattern(string $code): string
    {
        if (! self::isValidEan13($code)) {
            throw new \InvalidArgumentException("Código EAN-13 inválido: {$code}");
        }

        $first = (int) $code[0];
        $parity = self::PARITY[$first];

        $pattern = '101'; // guarda inicial
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $code[$i + 1];
            $pattern .= $parity[$i] === 'L' ? self::L[$digit] : self::G[$digit];
        }
        $pattern .= '01010'; // guarda central
        for ($i = 0; $i < 6; $i++) {
            $digit = (int) $code[$i + 7];
            $pattern .= self::R[$digit];
        }
        $pattern .= '101'; // guarda final

        return $pattern;
    }

    /**
     * Renderiza el EAN-13 como SVG (sin dependencias). Incluye el número en
     * texto legible debajo, con el primer dígito a la izquierda (estilo estándar).
     */
    public static function ean13Svg(string $code, float $moduleWidth = 2.0, float $barHeight = 60.0, bool $showText = true): string
    {
        $pattern = self::ean13Pattern($code);

        $quietLeft = 11;
        $quietRight = 7;
        $totalModules = $quietLeft + 95 + $quietRight;
        $textHeight = $showText ? max(10.0, $moduleWidth * 8) : 0.0;
        $guardExtend = $showText ? $textHeight * 0.55 : 0.0;

        $width = round($totalModules * $moduleWidth, 2);
        $height = round($barHeight + $textHeight, 2);

        // Posiciones (en módulos) de las barras de guarda, que se alargan hacia abajo.
        $guardRanges = [[0, 3], [45, 50], [92, 95]];
        $isGuard = static function (int $module) use ($guardRanges): bool {
            foreach ($guardRanges as [$start, $end]) {
                if ($module >= $start && $module < $end) {
                    return true;
                }
            }

            return false;
        };

        $bars = '';
        for ($i = 0; $i < 95; $i++) {
            if ($pattern[$i] !== '1') {
                continue;
            }
            $x = round(($quietLeft + $i) * $moduleWidth, 3);
            $h = round($barHeight + ($isGuard($i) ? $guardExtend : 0.0), 2);
            $bars .= '<rect x="'.$x.'" y="0" width="'.round($moduleWidth, 3).'" height="'.$h.'" fill="#111827"/>';
        }

        $text = '';
        if ($showText) {
            $fontSize = round($textHeight * 0.9, 2);
            $y = round($height - $textHeight * 0.1, 2);
            $leftGroupCenter = round(($quietLeft + 3 + 21) * $moduleWidth, 2);
            $rightGroupCenter = round(($quietLeft + 50 + 21) * $moduleWidth, 2);
            $firstX = round(($quietLeft - 9) * $moduleWidth, 2);

            $text .= '<g font-family="monospace" font-size="'.$fontSize.'" fill="#111827" text-anchor="middle">';
            $text .= '<text x="'.max(0, $firstX).'" y="'.$y.'" text-anchor="start">'.$code[0].'</text>';
            $text .= '<text x="'.$leftGroupCenter.'" y="'.$y.'" letter-spacing="'.round($moduleWidth * 0.6, 2).'">'.substr($code, 1, 6).'</text>';
            $text .= '<text x="'.$rightGroupCenter.'" y="'.$y.'" letter-spacing="'.round($moduleWidth * 0.6, 2).'">'.substr($code, 7, 6).'</text>';
            $text .= '</g>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' '.$height.'" width="'.$width.'" height="'.$height.'" shape-rendering="crispEdges">'
            .'<rect x="0" y="0" width="'.$width.'" height="'.$height.'" fill="#ffffff"/>'
            .$bars.$text
            .'</svg>';
    }
}
