<?php

namespace App\Filament\Boutique\Widgets;

use App\Models\ProductVariant;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Métricas clave del comercio: ventas del día, del mes, ticket promedio
 * mensual y variantes con inventario bajo. Todas las consultas quedan
 * aisladas por comercio gracias al TenantScope de los modelos.
 */
class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $inicioDia = Carbon::now()->startOfDay();
        $finDia = Carbon::now()->endOfDay();
        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        $ventasHoy = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$inicioDia, $finDia])
            ->sum('total');

        $ventasMes = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$inicioMes, $finMes])
            ->sum('total');

        $cantidadMes = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$inicioMes, $finMes])
            ->count();

        $ticketPromedio = $cantidadMes > 0 ? (float) $ventasMes / $cantidadMes : 0.0;

        $variantesBajoStock = ProductVariant::query()
            ->where('stock_quantity', '<=', 5)
            ->count();

        return [
            Stat::make('Ventas de hoy', $this->lempiras($ventasHoy))
                ->description('Total facturado hoy')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Ventas del mes', $this->lempiras($ventasMes))
                ->description($cantidadMes.' '.($cantidadMes === 1 ? 'venta registrada' : 'ventas registradas'))
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary'),
            Stat::make('Ticket promedio', $this->lempiras($ticketPromedio))
                ->description('Promedio por venta del mes')
                ->descriptionIcon('heroicon-m-receipt-percent'),
            Stat::make('Variantes bajo stock', (string) $variantesBajoStock)
                ->description('5 unidades o menos')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($variantesBajoStock > 0 ? 'danger' : 'gray'),
        ];
    }

    private function lempiras(float|int|string $monto): string
    {
        return 'L '.number_format((float) $monto, 2);
    }
}
