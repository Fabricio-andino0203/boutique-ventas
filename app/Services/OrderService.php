<?php

namespace App\Services;

use App\Actions\RegisterSale;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de pedidos: cálculo de totales, registro de abonos/pagos y, al quedar
 * totalmente pagado, generación de la venta (descontando stock vía RegisterSale).
 */
class OrderService
{
    /** Recalcula subtotal, descuento, impuesto adicional, total y estado de pago. */
    public function recomputeTotals(Order $order): void
    {
        $order->loadMissing('items.variant.product');

        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($order->items as $item) {
            $line = (float) $item->unit_price * (int) $item->quantity;
            if ((float) $item->subtotal !== $line) {
                $item->subtotal = $line;
                $item->saveQuietly();
            }
            $subtotal += $line;

            $product = $item->variant?->product;
            if ($product && $product->tax_type === 'additional') {
                $tax += $line * ((float) $product->tax_rate) / 100;
            }
        }

        $discount = $order->discount_type === 'percent'
            ? $subtotal * ((float) $order->discount_value) / 100
            : (float) $order->discount_value;
        $discount = max(0.0, min($discount, $subtotal));

        $order->subtotal = $subtotal;
        $order->discount_amount = $discount;
        $order->tax = $tax;
        $order->total = max(0.0, $subtotal - $discount + $tax);
        $order->payment_status = $this->paymentStatus($order);
        $order->saveQuietly();
    }

    private function paymentStatus(Order $order): string
    {
        $paid = (float) $order->paid;
        $total = (float) $order->total;

        if ($paid <= 0) {
            return 'unpaid';
        }

        return $paid + 0.001 >= $total ? 'paid' : 'partial';
    }

    /** Registra un abono/pago. Si el pedido queda saldado, genera la venta. */
    public function registerPayment(Order $order, float $amount, string $method = 'cash', ?User $user = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $amount, $method, $user): void {
            $order->paid = (float) $order->paid + $amount;
            $order->payment_status = $this->paymentStatus($order);
            $order->saveQuietly();

            $order->movements()->create([
                'user_id' => $user?->id ?? Auth::id(),
                'type' => 'payment',
                'description' => 'Pago registrado: L '.number_format($amount, 2).' ('.$this->methodLabel($method).')',
                'meta' => ['amount' => $amount, 'method' => $method],
            ]);

            if ($order->payment_status === 'paid' && ! $order->sale_id) {
                $this->generateSale($order, $method);
            }
        });
    }

    /** Genera la venta del pedido (descuenta stock) y la liga. */
    public function generateSale(Order $order, string $method = 'cash'): void
    {
        $order->loadMissing('items');

        $items = [];
        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                $items[] = [
                    'variant_id' => $item->product_variant_id,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                ];
            }
        }

        if ($items === []) {
            return;
        }

        $sale = app(RegisterSale::class)->execute($items, [
            'customer_id' => $order->customer_id,
            'payment_method' => $method,
            'amount_paid' => (float) $order->total,
            'discount' => (float) $order->discount_amount,
            'notes' => 'Pedido '.$order->number,
        ]);

        $order->sale_id = $sale->id;
        $order->saveQuietly();

        $order->movements()->create([
            'user_id' => Auth::id(),
            'type' => 'status',
            'description' => 'El pedido generó la venta '.$sale->number,
            'meta' => ['sale_id' => $sale->id],
        ]);
    }

    private function methodLabel(string $method): string
    {
        return match ($method) {
            'cash' => 'Efectivo',
            'card' => 'Tarjeta',
            'transfer' => 'Transferencia',
            default => $method,
        };
    }
}
