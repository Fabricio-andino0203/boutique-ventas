<?php

namespace App\Actions;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Purchase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una compra a proveedor de forma atómica: crea la compra y sus
 * líneas, incrementa las existencias de cada variante y deja el movimiento de
 * inventario con cantidad positiva.
 */
class RegisterPurchase
{
    /**
     * @param  array<int, array{variant_id:int, quantity:int, unit_cost:float}>  $items
     * @param  array{supplier_id?:int|null, purchased_at?:string|\DateTimeInterface|null, notes?:string|null}  $meta
     */
    public function execute(array $items, array $meta = []): Purchase
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un producto a la compra.']);
        }

        return DB::transaction(function () use ($items, $meta): Purchase {
            $total = 0.0;
            $prepared = [];

            foreach ($items as $item) {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()->lockForUpdate()->find($item['variant_id']);

                if ($variant === null) {
                    throw ValidationException::withMessages(['items' => 'Una variante ya no existe.']);
                }

                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    continue;
                }

                $unitCost = (float) $item['unit_cost'];
                $lineTotal = $unitCost * $quantity;
                $total += $lineTotal;

                $prepared[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $lineTotal,
                ];
            }

            if ($prepared === []) {
                throw ValidationException::withMessages(['items' => 'Agrega al menos un producto con cantidad válida.']);
            }

            $purchasedAt = isset($meta['purchased_at']) && $meta['purchased_at'] !== null
                ? Carbon::parse($meta['purchased_at'])
                : now();

            $purchase = Purchase::create([
                'supplier_id' => $meta['supplier_id'] ?? null,
                'user_id' => Auth::id(),
                'status' => 'received',
                'subtotal' => $total,
                'tax' => 0,
                'total' => $total,
                'purchased_at' => $purchasedAt,
                'received_at' => now(),
                'notes' => $meta['notes'] ?? null,
            ]);

            $purchase->update(['number' => 'C-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($prepared as $row) {
                /** @var ProductVariant $variant */
                $variant = $row['variant'];

                $purchase->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'subtotal' => $row['line_total'],
                ]);

                $variant->increment('stock_quantity', $row['quantity']);

                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'user_id' => Auth::id(),
                    'type' => 'purchase',
                    'quantity' => $row['quantity'],
                    'balance_after' => $variant->stock_quantity,
                    'source_type' => Purchase::class,
                    'source_id' => $purchase->id,
                    'notes' => "Compra {$purchase->number}",
                ]);
            }

            return $purchase;
        });
    }
}
