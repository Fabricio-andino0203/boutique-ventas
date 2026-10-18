<?php

namespace App\Filament\Boutique\Reportes;

use App\Models\SaleItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Collection;

/**
 * Tabla con los productos más rentables del período seleccionado (top 10 por
 * utilidad), agregando ingreso, costo y utilidad por producto.
 */
class TopProfitableProducts extends BaseWidget
{
    use InteractsWithPageFilters;
    use InteractsWithReportPeriod;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Productos más rentables')
            ->description('Top 10 por utilidad en el período')
            ->records(fn (): Collection => $this->productosRentables())
            ->columns([
                TextColumn::make('producto')
                    ->label('Producto'),
                TextColumn::make('unidades')
                    ->label('Unidades')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('ingreso')
                    ->label('Ingreso')
                    ->formatStateUsing(fn ($state): string => $this->lempiras($state))
                    ->alignEnd(),
                TextColumn::make('costo')
                    ->label('Costo')
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
            ->emptyStateIcon('heroicon-o-chart-bar');
    }

    /**
     * @return Collection<int, array{id:string, producto:string, unidades:int, ingreso:float, costo:float, utilidad:float}>
     */
    protected function productosRentables(): Collection
    {
        return $this->lineasDelPeriodo()
            ->groupBy(fn (SaleItem $linea): string => (string) ($linea->variant?->product_id ?? 'x'.$linea->product_variant_id))
            ->map(function (Collection $lineas, string $clave): array {
                $ingreso = (float) $lineas->sum(fn (SaleItem $l): float => (float) $l->quantity * (float) $l->unit_price);
                $costo = (float) $lineas->sum(fn (SaleItem $l): float => (float) $l->quantity * (float) ($l->variant?->cost ?? 0));

                return [
                    'id' => $clave,
                    'producto' => $lineas->first()->variant?->product?->name ?? 'Producto eliminado',
                    'unidades' => (int) $lineas->sum('quantity'),
                    'ingreso' => $ingreso,
                    'costo' => $costo,
                    'utilidad' => $ingreso - $costo,
                ];
            })
            ->sortByDesc('utilidad')
            ->take(10)
            ->values();
    }
}
