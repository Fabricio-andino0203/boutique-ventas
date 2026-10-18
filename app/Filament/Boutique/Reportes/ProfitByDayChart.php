<?php

namespace App\Filament\Boutique\Reportes;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Collection;

/**
 * Gráfica de barras con el total vendido por día dentro del período. La
 * agregación se hace en PHP sobre una consulta Eloquent (aislada por comercio)
 * para mantener la compatibilidad entre SQLite y PostgreSQL.
 */
class ProfitByDayChart extends ChartWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportPeriod;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Ventas por día';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    /**
     * Serie de ventas por día del período (hasta 92 días).
     *
     * @return Collection<int, array{etiqueta:string, ventas:float}>
     */
    protected function ventasPorDia(): Collection
    {
        $porFecha = $this->ventasDelPeriodo()
            ->groupBy(fn (Sale $venta): string => $venta->sold_at->toDateString())
            ->map(fn (Collection $grupo): float => (float) $grupo->sum('total'));

        $cursor = $this->periodoInicio();
        $fin = $this->periodoFin();
        $dias = collect();

        while ($cursor->lessThanOrEqualTo($fin) && $dias->count() < 92) {
            $dias->push([
                'etiqueta' => $cursor->format('d/m'),
                'ventas' => (float) $porFecha->get($cursor->toDateString(), 0.0),
            ]);
            $cursor->addDay();
        }

        return $dias;
    }

    protected function getData(): array
    {
        $serie = $this->ventasPorDia();

        return [
            'datasets' => [
                [
                    'label' => 'Ventas (L)',
                    'data' => $serie->pluck('ventas')->all(),
                    'backgroundColor' => 'rgba(244, 63, 120, 0.5)',
                    'borderColor' => '#F43F78',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $serie->pluck('etiqueta')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
