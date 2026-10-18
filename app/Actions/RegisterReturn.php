<?php

namespace App\Actions;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra la devolución de una venta de forma atómica: crea la devolución y
 * sus líneas, reingresa las existencias de cada variante y deja el movimiento
 * de inventario con cantidad positiva. Valida que lo devuelto por variante no
 * exceda lo vendido en la venta origen (descontando devoluciones previas).
 */
class RegisterReturn
{
    /**
     * @param  array<int, array{variant_id:int, quantity:int}>  $items
     * @param  array{returned_at?:string|\DateTimeInterface|null, reason?:string|null, notes?:string|null}  $meta
     */
    public function execute(Sale $sale, array $items, array $meta = []): SaleReturn
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un producto a la devolución.']);
        }

        return DB::transaction(function () use ($sale, $items, $meta): SaleReturn {
            $sale->loadMissing('items');

            /** @var array<int, array{quantity:int, unit_price:float}> $soldByVariant */
            $soldByVariant = [];

            foreach ($sale->items as $saleItem) {
                $variantId = (int) $saleItem->product_variant_id;
                $soldByVariant[$variantId] = [
                    'quantity' => ($soldByVariant[$variantId]['quantity'] ?? 0) + (int) $saleItem->quantity,
                    'unit_price' => (float) $saleItem->unit_price,
                    'sale_item_id' => $saleItem->id,
                ];
            }

            $alreadyReturned = $this->previouslyReturnedByVariant($sale);

            $total = 0.0;
            $prepared = [];

            foreach ($items as $item) {
                $variantId = (int) $item['variant_id'];
                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    continue;
                }

                if (! isset($soldByVariant[$variantId])) {
                    throw ValidationException::withMessages([
                        'items' => 'No puedes devolver una variante que no pertenece a la venta origen.',
                    ]);
                }

                $returnable = $soldByVariant[$variantId]['quantity'] - ($alreadyReturned[$variantId] ?? 0);

                if ($quantity > $returnable) {
                    throw ValidationException::withMessages([
                        'items' => "No puedes devolver más de lo vendido (disponibles para devolución: {$returnable}).",
                    ]);
                }

                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()->lockForUpdate()->find($variantId);

                if ($variant === null) {
                    throw ValidationException::withMessages(['items' => 'Una variante ya no existe.']);
                }

                $unitPrice = $soldByVariant[$variantId]['unit_price'];
                $lineTotal = $unitPrice * $quantity;
                $total += $lineTotal;

                $prepared[] = [
                    'variant' => $variant,
                    'sale_item_id' => $soldByVariant[$variantId]['sale_item_id'],
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if ($prepared === []) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos un producto con cantidad válida.']);
            }

            $returnedAt = isset($meta['returned_at']) && $meta['returned_at'] !== null
                ? Carbon::parse($meta['returned_at'])
                : now();

            $saleReturn = SaleReturn::create([
                'sale_id' => $sale->id,
                'user_id' => Auth::id(),
                'status' => 'completed',
                'reason' => $meta['reason'] ?? null,
                'total' => $total,
                'returned_at' => $returnedAt,
                'notes' => $meta['notes'] ?? null,
            ]);

            $saleReturn->update(['number' => 'D-'.str_pad((string) $saleReturn->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($prepared as $row) {
                /** @var ProductVariant $variant */
                $variant = $row['variant'];

                $saleReturn->items()->create([
                    'sale_item_id' => $row['sale_item_id'],
                    'product_variant_id' => $variant->id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'subtotal' => $row['line_total'],
                ]);

                $variant->increment('stock_quantity', $row['quantity']);

                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'user_id' => Auth::id(),
                    'type' => 'return',
                    'quantity' => $row['quantity'],
                    'balance_after' => $variant->stock_quantity,
                    'source_type' => SaleReturn::class,
                    'source_id' => $saleReturn->id,
                    'notes' => "Devolución {$saleReturn->number}",
                ]);
            }

            return $saleReturn;
        });
    }

    /**
     * Cantidades ya devueltas por variante para la venta dada.
     *
     * @return array<int, int>
     */
    private function previouslyReturnedByVariant(Sale $sale): array
    {
        $totals = [];

        $sale->returns()
            ->with('items')
            ->get()
            ->each(function (SaleReturn $return) use (&$totals): void {
                foreach ($return->items as $returnItem) {
                    $variantId = (int) $returnItem->product_variant_id;
                    $totals[$variantId] = ($totals[$variantId] ?? 0) + (int) $returnItem->quantity;
                }
            });

        return $totals;
    }
}
