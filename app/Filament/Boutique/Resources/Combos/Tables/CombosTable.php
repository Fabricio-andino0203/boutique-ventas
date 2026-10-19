<?php

namespace App\Filament\Boutique\Resources\Combos\Tables;

use App\Models\Combo;
use App\Services\ComboPricing;
use App\Services\ComboService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CombosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['items.product', 'items.variant', 'variant']))
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(config('filesystems.media'))
                    ->square()
                    ->defaultImageUrl(asset('images/brand/favicon.svg')),
                TextColumn::make('name')
                    ->label('Combo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Combo $record): string => $record->items->count().' producto(s)'),
                TextColumn::make('contiene')
                    ->label('Contiene')
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (Combo $record): array => self::contains($record)),
                TextColumn::make('por_separado')
                    ->label('Por separado')
                    ->alignEnd()
                    ->getStateUsing(fn (Combo $record): string => 'L '.number_format(app(ComboPricing::class)->separate($record), 2)),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('HNL')
                    ->sortable()
                    ->alignEnd()
                    ->description(function (Combo $record): ?string {
                        $savings = app(ComboPricing::class)->savings($record);

                        return $savings['percent'] > 0 ? 'Ahorra '.number_format($savings['percent'], 0).'%' : null;
                    }),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->badge()
                    ->color(fn (Combo $record): string => $record->stock_mode === 'own' ? 'warning' : 'info')
                    ->getStateUsing(function (Combo $record): string {
                        if ($record->stock_mode === 'own') {
                            return 'Propio: '.(int) $record->stock_quantity;
                        }

                        return 'Derivado: '.app(ComboService::class)->derivedAvailability($record);
                    }),
                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(fn (Combo $record) => $record->product?->delete()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(fn (Collection $records) => $records->each(fn (Combo $c) => $c->product?->delete())),
                ]),
            ])
            ->emptyStateHeading('Todavía no tenés combos')
            ->emptyStateDescription('Creá un combo para vender varios productos juntos a un precio especial.')
            ->emptyStateIcon(Heroicon::OutlinedRectangleStack);
    }

    /**
     * Chips de "Contiene": producto ×cantidad, con "a elegir" cuando la variante
     * se escoge al vender.
     *
     * @return array<int, string>
     */
    private static function contains(Combo $combo): array
    {
        return $combo->items->map(function ($item): string {
            $name = $item->product?->name ?? 'Producto';
            $qty = (int) $item->quantity > 1 ? ' ×'.(int) $item->quantity : '';
            $choose = $item->product_variant_id === null ? ' (a elegir)' : '';

            return $name.$qty.$choose;
        })->all();
    }
}
