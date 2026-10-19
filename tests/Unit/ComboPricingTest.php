<?php

namespace Tests\Unit;

use App\Services\ComboPricing;
use PHPUnit\Framework\TestCase;

class ComboPricingTest extends TestCase
{
    private ComboPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new ComboPricing;
    }

    public function test_redondea_al_multiplo_de_cinco_mas_cercano(): void
    {
        $this->assertSame(445.0, $this->pricing->roundPrice(443));
        $this->assertSame(450.0, $this->pricing->roundPrice(449));
        $this->assertSame(445.0, $this->pricing->roundPrice(445));
        $this->assertSame(500.0, $this->pricing->roundPrice(500));
        $this->assertSame(0.0, $this->pricing->roundPrice(2));
        $this->assertSame(5.0, $this->pricing->roundPrice(3));
    }

    public function test_precio_con_descuento_sobre_el_por_separado(): void
    {
        // 500 - 10% = 450 -> 450
        $this->assertSame(450.0, $this->pricing->discountedPrice(500, 10));
        // 500 - 15% = 425 -> 425
        $this->assertSame(425.0, $this->pricing->discountedPrice(500, 15));
        // 1234 - 10% = 1110.6 -> 1110
        $this->assertSame(1110.0, $this->pricing->discountedPrice(1234, 10));
    }
}
