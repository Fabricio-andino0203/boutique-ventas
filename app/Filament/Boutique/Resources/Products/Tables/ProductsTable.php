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
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'supplier']))
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(config('filesystems.media'))
                    ->square()
                    ->defaultImageUrl(asset('images/brand/favicon.svg')),
                TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Producto')
                    ->description(fn ($record): ?string => $record->brand)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'variable' ? 'Variable' : 'Simple')
                    ->color(fn (string $state): string => $state === 'variable' ? 'warning' : 'gray'),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Sin categoría'),
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
                TextColumn::make('stock_total')
                    ->label('Stock')
                    ->sum('variants', 'stock_quantity')
                    ->badge()
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
