<?php

namespace App\Filament\Boutique\Reportes;

use App\Models\Sale;
use App\Models\SaleItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

/**
 * Tabla con el desempeño de cada vendedor en el período: número de ventas,
 * monto vendido y utilidad (monto vendido menos costo de lo vendido).
 */
class SellerProfit extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportPeriod;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Ventas por vendedor')
            ->description('Desempeño de cada vendedor en el período')
            ->records(fn (): Collection => $this->ventasPorVendedor())
            ->columns([
                TextColumn::make('vendedor')
                    ->label('Vendedor'),
                TextColumn::make('cantidad')
                    ->label('Ventas')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('ventas')
                    ->label('Monto vendido')
                    ->formatStateUsing(fn ($state): string => $this->lempiras($state))
                    ->alignEnd(),
                TextColumn::make('utilidad')
                    ->label('Utilidad')
                    ->formatStateUsing(fn ($state): string => $this->lempiras($state))
                    ->weight('bold')
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger')
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Sin ventas en el período seleccionado')
            ->emptyStateIcon('heroicon-o-users');
    }

    /**
     * @return Collection<int, array{id:int, vendedor:string, cantidad:int, ventas:float, utilidad:float}>
     */
    protected function ventasPorVendedor(): Collection
    {
        $costoPorVendedor = $this->lineasDelPeriodo()
            ->groupBy(fn (SaleItem $linea): int => (int) ($linea->sale?->user_id ?? 0))
            ->map(fn (Collection $lineas): float => (float) $lineas->sum(
                fn (SaleItem $l): float => (float) $l->quantity * (float) ($l->variant?->cost ?? 0)
            ));

        return $this->ventasDelPeriodo()
            ->groupBy(fn (Sale $venta): int => (int) ($venta->user_id ?? 0))
            ->map(function (Collection $ventas, int|string $userId) use ($costoPorVendedor): array {
                $totalVentas = (float) $ventas->sum('total');
                $costo = (float) ($costoPorVendedor[(int) $userId] ?? 0);

                return [
                    'id' => (int) $userId,
                    'vendedor' => $ventas->first()->user?->name ?? 'Sin asignar',
                    'cantidad' => $ventas->count(),
                    'ventas' => $totalVentas,
                    'utilidad' => $totalVentas - $costo,
                ];
            })
            ->sortByDesc('utilidad')
            ->values();
    }
}
