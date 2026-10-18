<?php

namespace App\Filament\Boutique\Widgets;

use App\Models\ProductVariant;
use App\Models\SaleItem;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Tabla con los 10 productos más vendidos de los últimos 30 días, agregando
 * las unidades por variante. Se consulta vía Eloquent para que el TenantScope
 * aísle los datos del comercio activo.
 */
class TopProducts extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Productos más vendidos (últimos 30 días)')
            ->records(fn (): Collection => $this->productosMasVendidos())
            ->columns([
                TextColumn::make('producto')
                    ->label('Producto'),
                TextColumn::make('variante')
                    ->label('Variante')
                    ->placeholder('—'),
                TextColumn::make('unidades')
                    ->label('Unidades vendidas')
                    ->numeric()
                    ->alignEnd(),
            ])
            ->paginated(false)
            ->emptyStateHeading('Sin ventas en los últimos 30 días')
            ->emptyStateIcon('heroicon-o-chart-bar');
    }

    /**
     * @return Collection<int, array{id:int, producto:string, variante:?string, unidades:int}>
     */
    private function productosMasVendidos(): Collection
    {
        $desde = Carbon::now()->subDays(30)->startOfDay();

        $agregados = SaleItem::query()
            ->whereHas('sale', fn (Builder $query) => $query
                ->where('status', 'completed')
                ->where('sold_at', '>=', $desde))
            ->selectRaw('product_variant_id, SUM(quantity) as unidades')
            ->groupBy('product_variant_id')
            ->orderByDesc('unidades')
            ->limit(10)
            ->get();

        $variantes = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $agregados->pluck('product_variant_id'))
            ->get()
            ->keyBy('id');

        return $agregados->map(function (SaleItem $fila) use ($variantes): array {
            $variante = $variantes->get($fila->product_variant_id);

            $atributos = collect([$variante?->size, $variante?->color, $variante?->model])
                ->filter()
                ->implode(' · ');

            return [
                'id' => (int) $fila->product_variant_id,
                'producto' => $variante?->product?->name ?? 'Producto eliminado',
                'variante' => $atributos !== '' ? $atributos : null,
                'unidades' => (int) $fila->unidades,
            ];
        })->values();
    }
}
