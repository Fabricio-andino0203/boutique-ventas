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

    public function test_redondea_al_medio_millar_mas_cercano(): void
    {
        $this->assertSame(500.0, $this->pricing->roundToHalfThousand(499));
        $this->assertSame(500.0, $this->pricing->roundToHalfThousand(600));
        $this->assertSame(1000.0, $this->pricing->roundToHalfThousand(800));
        $this->assertSame(1000.0, $this->pricing->roundToHalfThousand(1249));
        $this->assertSame(1500.0, $this->pricing->roundToHalfThousand(1250));
        $this->assertSame(0.0, $this->pricing->roundToHalfThousand(120));
    }

    public function test_precio_con_descuento_sobre_el_por_separado(): void
    {
        // 2000 - 10% = 1800 -> redondeado al medio millar = 2000
        $this->assertSame(2000.0, $this->pricing->discountedPrice(2000, 10));
        // 2000 - 20% = 1600 -> 1500
        $this->assertSame(1500.0, $this->pricing->discountedPrice(2000, 20));
        // 3000 - 15% = 2550 -> 2500
        $this->assertSame(2500.0, $this->pricing->discountedPrice(3000, 15));
    }
}
