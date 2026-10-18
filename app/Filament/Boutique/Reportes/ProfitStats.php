<?php

namespace App\Filament\Boutique\Reportes;

use App\Models\SaleItem;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Métricas de utilidad del período seleccionado en la página de Reportes:
 * ventas totales, costo de lo vendido, utilidad bruta, margen, número de
 * ventas y ticket promedio. Todo en HNL.
 */
class ProfitStats extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportPeriod;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array{ventas:float, costo:float, utilidad:float, margen:float, cantidad:int, ticket:float}
     */
    protected function metricas(): array
    {
        $ventas = (float) $this->ventasDelPeriodo()->sum('total');
        $cantidad = $this->ventasDelPeriodo()->count();
        $costo = (float) $this->lineasDelPeriodo()->sum(
            fn (SaleItem $linea): float => (float) $linea->quantity * (float) ($linea->variant?->cost ?? 0)
        );
        $utilidad = $ventas - $costo;

        return [
            'ventas' => $ventas,
            'costo' => $costo,
            'utilidad' => $utilidad,
            'margen' => $ventas > 0 ? $utilidad / $ventas * 100 : 0.0,
            'cantidad' => $cantidad,
            'ticket' => $cantidad > 0 ? $ventas / $cantidad : 0.0,
        ];
    }

    protected function getStats(): array
    {
        $metricas = $this->metricas();
        $utilidadPositiva = $metricas['utilidad'] >= 0;

        return [
            Stat::make('Ventas totales', $this->lempiras($metricas['ventas']))
                ->description($metricas['cantidad'].' '.($metricas['cantidad'] === 1 ? 'venta en el período' : 'ventas en el período'))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
            Stat::make('Costo de lo vendido', $this->lempiras($metricas['costo']))
                ->description('Costo de las unidades vendidas')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('gray'),
            Stat::make('Utilidad bruta', $this->lempiras($metricas['utilidad']))
                ->description('Ventas menos costo')
                ->descriptionIcon($utilidadPositiva ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($utilidadPositiva ? 'success' : 'danger'),
            Stat::make('Margen bruto', number_format($metricas['margen'], 1).'%')
                ->description('Utilidad sobre ventas')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color($metricas['margen'] >= 0 ? 'success' : 'danger'),
            Stat::make('Número de ventas', number_format($metricas['cantidad']))
                ->description('Transacciones completadas')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('gray'),
            Stat::make('Ticket promedio', $this->lempiras($metricas['ticket']))
                ->description('Promedio por venta')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }

    protected function getColumns(): int
    {
        return 3;
    }
}
