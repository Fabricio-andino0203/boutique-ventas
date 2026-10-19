@php
    $w = (int) ($options['labelWidth'] ?? 50);
    $h = (int) ($options['labelHeight'] ?? 25);
    $perRow = max(1, (int) ($options['perRow'] ?? 1));
    $showName = (bool) ($options['showName'] ?? true);
    $showPrice = (bool) ($options['showPrice'] ?? true);
    $belowBars = $options['belowBars'] ?? 'code';
    $thermal = ($options['printer'] ?? 'normal') === 'thermal';
    $currency = fn ($n) => 'L ' . number_format((float) $n, 2);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas — {{ $tenant->name ?? 'Boutique' }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f4f6;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
            color: #111827;
        }
        .toolbar {
            position: sticky; top: 0;
            display: flex; gap: .75rem; align-items: center; justify-content: space-between;
            padding: .75rem 1rem; background: #ffffff; border-bottom: 1px solid #e5e7eb;
        }
        .toolbar .count { font-size: .875rem; color: #6b7280; }
        .btn {
            border: 0; border-radius: .5rem; padding: .5rem 1rem; font-weight: 600; cursor: pointer;
            font-size: .875rem;
        }
        .btn-print { background: #F43F78; color: #fff; }
        .btn-back { background: #e5e7eb; color: #111827; text-decoration: none; }
        .sheet {
            padding: 10mm;
            display: grid;
            grid-template-columns: repeat({{ $perRow }}, {{ $w }}mm);
            gap: {{ $thermal ? '0' : '2mm' }};
            justify-content: center;
        }
        .label {
            width: {{ $w }}mm; height: {{ $h }}mm;
            border: {{ $thermal ? '0' : '1px dashed #d1d5db' }};
            padding: 1.2mm;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            overflow: hidden; text-align: center; background: #fff;
            page-break-inside: avoid;
        }
        .label .name {
            font-size: 2.2mm; font-weight: 600; line-height: 1.05;
            max-height: 5mm; overflow: hidden; width: 100%;
        }
        .label svg { width: 100%; height: auto; max-height: {{ $h * 0.5 }}mm; }
        .label .below { font-size: 2mm; font-family: monospace; letter-spacing: .3mm; margin-top: .3mm; }
        .label .price { font-size: 2.6mm; font-weight: 700; margin-top: .3mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { padding: 0; }
            @page { margin: {{ $thermal ? '0' : '8mm' }}; size: {{ $thermal ? $w . 'mm ' . $h . 'mm' : 'auto' }}; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('filament.boutique.pages.barcodes') }}" class="btn btn-back">← Volver</a>
        <span class="count">{{ count($labels) }} etiqueta(s) · {{ $w }}×{{ $h }} mm · {{ $thermal ? 'térmica' : 'hoja normal' }}</span>
        <button type="button" class="btn btn-print" onclick="window.print()">Imprimir etiquetas</button>
    </div>

    <div class="sheet">
        @foreach ($labels as $label)
            <div class="label">
                @if ($showName)
                    <div class="name">{{ $label['name'] }}</div>
                @endif
                {!! $label['svg'] !!}
                <div class="below">{{ $belowBars === 'name' ? $label['name'] : $label['code'] }}</div>
                @if ($showPrice)
                    <div class="price">{{ $currency($label['price']) }}</div>
                @endif
            </div>
        @endforeach
    </div>

</body>
</html>
