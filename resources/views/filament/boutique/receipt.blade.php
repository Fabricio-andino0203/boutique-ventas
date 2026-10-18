@php
    /** @var \App\Models\Sale $sale */
    /** @var \App\Models\Tenant|null $tenant */
    $storeName = $tenant?->business_name ?: ($tenant?->name ?? 'BOUTIQUE VENTAS');
    $paymentLabel = match ($sale->payment_method) {
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        default => $sale->payment_method,
    };
    $money = fn ($value): string => 'L '.number_format((float) $value, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recibo {{ $sale->number }}</title>
    <style>
        :root {
            --ink: #1f2937;
            --muted: #6b7280;
            --line: #e5e7eb;
            --brand: #5b1e3d;
            --accent: #f43f78;
        }

        * { box-sizing: border-box; }

        html, body {
            margin: 0;
            padding: 0;
            background: #f3f4f6;
            color: var(--ink);
            font-family: 'Poppins', 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        .toolbar {
            max-width: 80mm;
            margin: 16px auto 0;
            display: flex;
            gap: 8px;
            justify-content: center;
        }

        .toolbar button {
            cursor: pointer;
            border: none;
            border-radius: 8px;
            padding: 9px 18px;
            font: inherit;
            font-weight: 600;
            color: #fff;
            background: var(--accent);
        }

        .toolbar button:hover { background: #d62f66; }

        .ticket {
            width: 80mm;
            max-width: 80mm;
            margin: 16px auto 32px;
            background: #fff;
            padding: 18px 16px;
            box-shadow: 0 1px 8px rgba(17, 24, 39, .12);
        }

        .store {
            text-align: center;
            border-bottom: 1px dashed var(--line);
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        .store h1 {
            margin: 0 0 2px;
            font-size: 16px;
            letter-spacing: .3px;
            color: var(--brand);
        }

        .store p {
            margin: 1px 0;
            font-size: 11px;
            color: var(--muted);
        }

        .meta {
            font-size: 11px;
            margin-bottom: 12px;
        }

        .meta .row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 1px 0;
        }

        .meta .row span:first-child { color: var(--muted); }
        .meta .row span:last-child { font-weight: 600; text-align: right; }

        .meta .number {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: .5px;
        }

        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 6px 0 10px;
        }

        table.items thead th {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .4px;
            color: var(--muted);
            text-align: right;
            padding: 4px 0;
            border-bottom: 1px solid var(--line);
        }

        table.items thead th:first-child { text-align: left; }

        table.items td {
            padding: 5px 0;
            vertical-align: top;
            border-bottom: 1px dotted var(--line);
            text-align: right;
        }

        table.items td.name { text-align: left; padding-right: 6px; }
        table.items .variant { display: block; font-size: 10px; color: var(--muted); }

        .totals {
            margin-top: 8px;
            font-size: 12px;
        }

        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }

        .totals .row span:first-child { color: var(--muted); }

        .totals .grand {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--ink);
            font-size: 15px;
            font-weight: 700;
        }

        .totals .grand span:first-child { color: var(--ink); }

        .thanks {
            text-align: center;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px dashed var(--line);
            font-weight: 600;
            color: var(--brand);
        }

        .thanks small {
            display: block;
            font-weight: 400;
            color: var(--muted);
            margin-top: 2px;
        }

        @media print {
            @page { margin: 0; }
            html, body { background: #fff; }
            .toolbar { display: none; }
            .ticket {
                margin: 0;
                box-shadow: none;
                width: auto;
                padding: 0 2mm;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    <div class="ticket">
        <div class="store">
            <h1>{{ $storeName }}</h1>
            @if ($tenant?->address)
                <p>{{ $tenant->address }}{{ $tenant->city ? ', '.$tenant->city : '' }}</p>
            @elseif ($tenant?->city)
                <p>{{ $tenant->city }}</p>
            @endif
            @if ($tenant?->phone)
                <p>Tel. {{ $tenant->phone }}</p>
            @endif
        </div>

        <div class="meta">
            <div class="number">Recibo {{ $sale->number }}</div>
            <div class="row">
                <span>Fecha</span>
                <span>{{ $sale->sold_at?->format('d/m/Y H:i') }}</span>
            </div>
            <div class="row">
                <span>Vendedor</span>
                <span>{{ $sale->user?->name ?? '—' }}</span>
            </div>
            <div class="row">
                <span>Cliente</span>
                <span>{{ $sale->customer?->name ?? 'Consumidor final' }}</span>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Cant.</th>
                    <th>P. unit.</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    @php
                        $variant = $item->variant;
                        $attrs = collect([$variant?->size, $variant?->color, $variant?->model])->filter()->implode(' / ');
                    @endphp
                    <tr>
                        <td class="name">
                            {{ $variant?->product?->name ?? 'Producto' }}
                            @if ($attrs !== '')
                                <span class="variant">{{ $attrs }}</span>
                            @endif
                        </td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ $money($item->unit_price) }}</td>
                        <td>{{ $money($item->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row">
                <span>Subtotal</span>
                <span>{{ $money($sale->subtotal) }}</span>
            </div>
            @if ((float) $sale->discount > 0)
                <div class="row">
                    <span>Descuento</span>
                    <span>- {{ $money($sale->discount) }}</span>
                </div>
            @endif
            <div class="row grand">
                <span>Total</span>
                <span>{{ $money($sale->total) }}</span>
            </div>
            <div class="row">
                <span>Pago ({{ $paymentLabel }})</span>
                <span>{{ $money($sale->amount_paid) }}</span>
            </div>
            <div class="row">
                <span>Cambio</span>
                <span>{{ $money($sale->change) }}</span>
            </div>
        </div>

        <div class="thanks">
            ¡Gracias por su compra!
            <small>{{ $storeName }}</small>
        </div>
    </div>
</body>
</html>
