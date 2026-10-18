<?php

namespace App\Filament\Boutique\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Gráfica de líneas con el total vendido por día en los últimos 14 días.
 * La agregación se hace en PHP sobre una consulta Eloquent (aislada por
 * comercio) para mantener la compatibilidad entre SQLite y PostgreSQL.
 */
class SalesChart extends ChartWidget
{
    protected ?string $heading = 'Ventas por día (últimos 14 días)';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected function getData(): array
    {
        $inicio = Carbon::now()->subDays(13)->startOfDay();

        $ventasPorDia = Sale::query()
            ->where('status', 'completed')
            ->where('sold_at', '>=', $inicio)
            ->get(['sold_at', 'total'])
            ->groupBy(fn (Sale $venta): string => $venta->sold_at->format('Y-m-d'))
            ->map(fn (iterable $grupo): float => (float) collect($grupo)->sum('total'));

        $etiquetas = [];
        $datos = [];

        for ($dia = 0; $dia < 14; $dia++) {
            $fecha = $inicio->copy()->addDays($dia);
            $etiquetas[] = $fecha->format('d/m');
            $datos[] = $ventasPorDia->get($fecha->format('Y-m-d'), 0.0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ventas (L)',
                    'data' => $datos,
                    'borderColor' => '#F43F78',
                    'backgroundColor' => 'rgba(244, 63, 120, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $etiquetas,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
