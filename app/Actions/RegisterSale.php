<?php

namespace App\Actions;

use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una venta del POS de forma atómica: crea la venta y sus líneas,
 * descuenta las existencias de cada variante y deja el movimiento de inventario.
 */
class RegisterSale
{
    /**
     * @param  array<int, array{variant_id:int, quantity:int, unit_price:float}>  $items
     * @param  array{customer_id?:int|null, payment_method?:string, amount_paid?:float, discount?:float, notes?:string|null, cash_register_id?:int|null}  $meta
     */
    public function execute(array $items, array $meta = []): Sale
    {
        if ($items === []) {
            throw ValidationException::withMessages(['cart' => 'Agrega al menos un producto a la venta.']);
        }

        return DB::transaction(function () use ($items, $meta): Sale {
            $subtotal = 0.0;
            $prepared = [];

            foreach ($items as $item) {
                /** @var ProductVariant|null $variant */
                $variant = ProductVariant::query()->lockForUpdate()->find($item['variant_id']);

                if ($variant === null) {
                    throw ValidationException::withMessages(['cart' => 'Una variante ya no existe.']);
                }

                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    continue;
                }

                if ($variant->stock_quantity < $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Sin existencias suficientes de {$variant->product->name} (disponibles: {$variant->stock_quantity}).",
                    ]);
                }

                $unitPrice = (float) $item['unit_price'];
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;

                $prepared[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $discount = max(0.0, (float) ($meta['discount'] ?? 0));
            $total = max(0.0, $subtotal - $discount);
            $amountPaid = (float) ($meta['amount_paid'] ?? $total);

            $sale = Sale::create([
                'cash_register_id' => $meta['cash_register_id'] ?? null,
                'customer_id' => $meta['customer_id'] ?? null,
                'user_id' => Auth::id(),
                'status' => 'completed',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => 0,
                'total' => $total,
                'payment_method' => $meta['payment_method'] ?? 'cash',
                'amount_paid' => $amountPaid,
                'change' => max(0.0, $amountPaid - $total),
                'sold_at' => now(),
                'notes' => $meta['notes'] ?? null,
            ]);

            $sale->update(['number' => 'V-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($prepared as $row) {
                /** @var ProductVariant $variant */
                $variant = $row['variant'];

                $sale->items()->create([
                    'product_variant_id' => $variant->id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'unit_cost' => $variant->cost,
                    'discount' => 0,
                    'subtotal' => $row['line_total'],
                ]);

                $variant->decrement('stock_quantity', $row['quantity']);

                InventoryMovement::create([
                    'product_variant_id' => $variant->id,
                    'user_id' => Auth::id(),
                    'type' => 'sale',
                    'quantity' => -$row['quantity'],
                    'balance_after' => $variant->stock_quantity,
                    'source_type' => Sale::class,
                    'source_id' => $sale->id,
                    'notes' => "Venta {$sale->number}",
                ]);
            }

            return $sale;
        });
    }
}
