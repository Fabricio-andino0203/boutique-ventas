<?php

namespace App\Filament\Boutique\Reportes;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resuelve el rango de fechas del reporte a partir de los filtros de la página
 * (`$this->pageFilters`) y expone las consultas base del período, ya aisladas
 * por comercio gracias al TenantScope de los modelos. Está pensado para
 * combinarse con InteractsWithPageFilters en los widgets de Reportes.
 */
trait InteractsWithReportPeriod
{
    /** @var Collection<int, Sale>|null */
    private ?Collection $ventasCache = null;

    /** @var Collection<int, SaleItem>|null */
    private ?Collection $lineasCache = null;

    protected function periodoInicio(): Carbon
    {
        $desde = $this->pageFilters['desde'] ?? null;

        return Carbon::parse($desde ?: Carbon::now()->startOfMonth())->startOfDay();
    }

    protected function periodoFin(): Carbon
    {
        $hasta = $this->pageFilters['hasta'] ?? null;
        $fin = Carbon::parse($hasta ?: Carbon::now()->endOfMonth())->endOfDay();
        $inicio = $this->periodoInicio();

        // Evita rangos invertidos: si "hasta" es anterior a "desde", usa el día de inicio.
        return $fin->lessThan($inicio) ? $inicio->copy()->endOfDay() : $fin;
    }

    /**
     * Ventas completadas del comercio dentro del rango seleccionado.
     *
     * @return Collection<int, Sale>
     */
    protected function ventasDelPeriodo(): Collection
    {
        return $this->ventasCache ??= Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$this->periodoInicio(), $this->periodoFin()])
            ->with('user:id,name')
            ->get(['id', 'user_id', 'total', 'sold_at']);
    }

    /**
     * Líneas vendidas del período con su variante y producto, para calcular el
     * costo (variante.cost × cantidad) y los ingresos por producto/vendedor.
     *
     * @return Collection<int, SaleItem>
     */
    protected function lineasDelPeriodo(): Collection
    {
        return $this->lineasCache ??= SaleItem::query()
            ->whereHas('sale', fn (Builder $query) => $query
                ->where('status', 'completed')
                ->whereBetween('sold_at', [$this->periodoInicio(), $this->periodoFin()]))
            ->with(['variant:id,product_id,size,color,model,cost', 'variant.product:id,name', 'sale:id,user_id'])
            ->get();
    }

    protected function lempiras(float|int|string $monto): string
    {
        return 'L '.number_format((float) $monto, 2);
    }
}
