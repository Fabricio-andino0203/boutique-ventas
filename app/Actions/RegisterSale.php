<?php

namespace App\Actions;

use App\Models\CashRegister;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra una venta del POS de forma atómica: crea la venta y sus líneas,
 * descuenta las existencias de cada variante y deja el movimiento de inventario.
 *
 * Soporta combos: una línea puede traer `combo_id` y, si es de stock derivado,
 * una lista `components` [{variant_id, quantity}]. En ese caso la línea de venta
 * se registra sobre la variante combo (unit_price = precio del combo) pero el
 * inventario que se descuenta es el de los componentes, no el de la variante combo.
 */
class RegisterSale
{
    /**
     * @param  array<int, array{variant_id:int, quantity:int, unit_price:float, unit_cost?:float, combo_id?:int|null, components?:array<int, array{variant_id:int, quantity:int}>}>  $items
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
                $variant = ProductVariant::query()->lockForUpdate()->with('product')->find($item['variant_id']);

                if ($variant === null) {
                    throw ValidationException::withMessages(['cart' => 'Una variante ya no existe.']);
                }

                $quantity = (int) $item['quantity'];

                if ($quantity < 1) {
                    continue;
                }

                $components = $this->resolveComponents($item['components'] ?? null, $quantity);

                // Combo derivado: valida el stock de los componentes; la variante
                // combo no gestiona stock propio.
                if ($components === null) {
                    if ($variant->stock_quantity < $quantity) {
                        throw ValidationException::withMessages([
                            'cart' => "Sin existencias suficientes de {$variant->product->name} (disponibles: {$variant->stock_quantity}).",
                        ]);
                    }
                }

                $unitPrice = (float) $item['unit_price'];
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;

                $prepared[] = [
                    'variant' => $variant,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => (float) ($item['unit_cost'] ?? $variant->cost),
                    'line_total' => $lineTotal,
                    'components' => $components,
                ];
            }

            $discount = max(0.0, (float) ($meta['discount'] ?? 0));
            $total = max(0.0, $subtotal - $discount);
            $amountPaid = (float) ($meta['amount_paid'] ?? $total);

            // Liga la venta a la caja abierta del vendedor si no se indicó una explícita.
            $cashRegisterId = $meta['cash_register_id'] ?? CashRegister::query()
                ->where('opened_by', Auth::id())
                ->where('status', 'open')
                ->value('id');

            $sale = Sale::create([
                'cash_register_id' => $cashRegisterId,
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
                    'unit_cost' => $row['unit_cost'],
                    'discount' => 0,
                    'subtotal' => $row['line_total'],
                ]);

                if ($row['components'] !== null) {
                    // Stock derivado: descuenta los componentes del combo.
                    foreach ($row['components'] as $component) {
                        $this->discountVariant(
                            $component['variant'],
                            $component['quantity'],
                            $sale,
                            "Combo {$variant->product->name} en venta {$sale->number}",
                        );
                    }

                    continue;
                }

                // Variante normal (incluye combos de stock propio).
                $this->discountVariant($variant, $row['quantity'], $sale, "Venta {$sale->number}");
            }

            return $sale;
        });
    }

    /**
     * Resuelve y bloquea las variantes componentes de un combo derivado,
     * validando existencias. Devuelve null si la línea no es un combo derivado.
     *
     * @param  array<int, array{variant_id:int, quantity:int}>|null  $components
     * @return array<int, array{variant:ProductVariant, quantity:int}>|null
     */
    private function resolveComponents(?array $components, int $comboQuantity): ?array
    {
        if ($components === null || $components === []) {
            return null;
        }

        $resolved = [];

        foreach ($components as $component) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::query()->lockForUpdate()->with('product')->find($component['variant_id']);

            if ($variant === null) {
                throw ValidationException::withMessages(['cart' => 'Un producto del combo ya no existe.']);
            }

            $needed = max(1, (int) $component['quantity']) * $comboQuantity;

            if ($variant->stock_quantity < $needed) {
                throw ValidationException::withMessages([
                    'cart' => "Sin existencias suficientes de {$variant->product->name} para el combo (disponibles: {$variant->stock_quantity}).",
                ]);
            }

            $resolved[] = ['variant' => $variant, 'quantity' => $needed];
        }

        return $resolved;
    }

    /** Descuenta una variante y registra el movimiento de inventario de la venta. */
    private function discountVariant(ProductVariant $variant, int $quantity, Sale $sale, string $notes): void
    {
        $variant->decrement('stock_quantity', $quantity);

        InventoryMovement::create([
            'product_variant_id' => $variant->id,
            'user_id' => Auth::id(),
            'type' => 'sale',
            'quantity' => -$quantity,
            'balance_after' => $variant->stock_quantity,
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'notes' => $notes,
        ]);
    }
}
