<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\ProductVariant;

/**
 * Lógica de precio de los combos (sin efectos secundarios, para poder testearla):
 * suma "por separado", recargo por variante según el modo de cobro, precio con
 * descuento (redondeado al medio millar como en Scrampi) y ahorro del cliente.
 */
class ComboPricing
{
    /**
     * Suma de los componentes "por separado": por cada línea, el precio
     * representativo (variante fija, o la más barata elegible) × cantidad.
     */
    public function separate(Combo $combo): float
    {
        $combo->loadMissing('items');

        $total = 0.0;

        foreach ($combo->items as $item) {
            $total += $this->representativePrice($item) * max(1, (int) $item->quantity);
        }

        return round($total, 2);
    }

    /**
     * Precio de referencia de un componente: la variante fija, o la más barata
     * de las elegibles cuando se elige al vender.
     */
    public function representativePrice(ComboItem $item): float
    {
        $prices = $item->eligibleVariants()->map(fn (ProductVariant $v): float => (float) $v->price);

        return $prices->isNotEmpty() ? (float) $prices->min() : 0.0;
    }

    /**
     * Recargo que paga el cliente por elegir una variante concreta en un
     * componente "a elegir", según el modo de cobro del combo.
     */
    public function surcharge(Combo $combo, ComboItem $item, ProductVariant $chosen): float
    {
        if (! $item->isChooseAtSale()) {
            return 0.0;
        }

        $surcharge = match ($combo->pricing_mode) {
            'up_to_price' => (float) $chosen->price - (float) $combo->base_price,
            'all_same' => 0.0,
            default => (float) $chosen->price - $this->representativePrice($item), // cheapest
        };

        return round(max(0.0, $surcharge), 2);
    }

    /**
     * Precio total a cobrar por un combo dada la selección de variantes
     * (mapa combo_item_id => ProductVariant elegida).
     *
     * @param  array<int, ProductVariant>  $chosenByItemId
     */
    public function priceForSelections(Combo $combo, array $chosenByItemId): float
    {
        $combo->loadMissing('items');

        $total = (float) $combo->price;

        foreach ($combo->items as $item) {
            $chosen = $chosenByItemId[$item->id] ?? null;

            if ($chosen instanceof ProductVariant) {
                $total += $this->surcharge($combo, $item, $chosen);
            }
        }

        return round($total, 2);
    }

    /**
     * Lo que ahorra el cliente respecto a comprar por separado.
     *
     * @return array{amount: float, percent: float}
     */
    public function savings(Combo $combo): array
    {
        $separate = $this->separate($combo);
        $amount = round(max(0.0, $separate - (float) $combo->price), 2);
        $percent = $separate > 0 ? round(($amount / $separate) * 100, 1) : 0.0;

        return ['amount' => $amount, 'percent' => $percent];
    }

    /**
     * Precio del combo tras aplicar un % de descuento sobre el "por separado",
     * redondeado al medio millar (500) como en Scrampi.
     */
    public function discountedPrice(float $separate, float $percent): float
    {
        return $this->roundToHalfThousand($separate * (1 - $percent / 100));
    }

    /** Redondea al medio millar (múltiplo de 500) más cercano. */
    public function roundToHalfThousand(float $value): float
    {
        return round($value / 500) * 500;
    }
}
