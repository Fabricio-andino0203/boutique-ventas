<?php

namespace App\Filament\Boutique\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->circular(),
                TextColumn::make('name')
                    ->label('Producto')
                    ->description(fn ($record): ?string => $record->brand)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Categoría')
                    ->badge()
                    ->color('primary')
                    ->placeholder('Sin categoría'),
                TextColumn::make('variants_count')
                    ->label('Variantes')
                    ->counts('variants')
                    ->alignCenter(),
                TextColumn::make('default_price')
                    ->label('Precio')
                    ->money('HNL')
                    ->sortable(),
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
