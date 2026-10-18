<?php

namespace App\Filament\Boutique\Resources\InventoryMovements\Tables;

use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleReturn;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['variant.product', 'user']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('variant.product.name')
                    ->label('Producto')
                    ->description(fn ($record): ?string => self::variantDetail($record))
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sale' => 'info',
                        'purchase' => 'success',
                        'return' => 'warning',
                        'adjustment' => 'gray',
                        'initial' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => self::typeLabel($state)),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->alignCenter()
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('source_type')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => self::sourceLabel($state))
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'sale' => 'Venta',
                        'purchase' => 'Compra',
                        'return' => 'Devolución',
                        'adjustment' => 'Ajuste',
                        'initial' => 'Inicial',
                    ]),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('desde')
                            ->label('Desde'),
                        DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'sale' => 'Venta',
            'purchase' => 'Compra',
            'return' => 'Devolución',
            'adjustment' => 'Ajuste',
            'initial' => 'Inicial',
            default => ucfirst($type),
        };
    }

    public static function sourceLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            Sale::class => 'Venta',
            Purchase::class => 'Compra',
            SaleReturn::class => 'Devolución',
            null => 'Ajuste manual',
            default => class_basename($sourceType),
        };
    }

    private static function variantDetail($record): ?string
    {
        $variant = $record->variant;

        if ($variant === null) {
            return null;
        }

        $parts = array_filter([$variant->size, $variant->color, $variant->model]);

        return $parts === [] ? $variant->sku : implode(' · ', $parts);
    }
}
