<?php

namespace App\Filament\Boutique\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->where('type', '!=', 'combo')->with(['category', 'supplier', 'brand']))
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(config('filesystems.media'))
                    ->square()
                    ->defaultImageUrl(asset('images/brand/favicon.svg')),
                TextColumn::make('code')
                    ->label('Código')
                    ->getStateUsing(fn ($record): ?string => $record->code ?: $record->barcode)
                    ->placeholder('—')
                    ->searchable(['code', 'barcode'])
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'variable' => 'Variable',
                        'custom' => 'Precio personalizado',
                        default => 'Simple',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'variable' => 'warning',
                        'custom' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Sin categoría'),
                TextColumn::make('brand.name')
                    ->label('Marca')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('unit')
                    ->label('Unidad')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('default_price')
                    ->label('Precio')
                    ->money('HNL')
                    ->sortable(),
                TextColumn::make('variants_sum_stock_quantity')
                    ->label('Stock')
                    ->sum('variants', 'stock_quantity')
                    ->default(0)
                    ->formatStateUsing(fn ($state): string => (string) (int) $state)
                    ->badge()
                    ->color(fn ($state): string => (int) $state <= 0 ? 'danger' : 'success')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
