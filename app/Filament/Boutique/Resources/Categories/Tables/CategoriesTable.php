<?php

namespace App\Filament\Boutique\Resources\Categories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('parent_id'))
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(config('filesystems.media'))
                    ->square()
                    ->defaultImageUrl(asset('images/brand/favicon.svg')),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn ($record): ?string => filled($record->description) ? Str::limit($record->description, 60) : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('children_count')
                    ->label('Subcategorías')
                    ->counts('children')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
                TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
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
