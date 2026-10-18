<?php

namespace App\Filament\Boutique\Resources\SaleReturns\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleReturnsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['sale', 'user']))
            ->columns([
                TextColumn::make('number')
                    ->label('N.º')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sale.number')
                    ->label('Venta')
                    ->searchable(),
                TextColumn::make('returned_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('HNL')
                    ->sortable(),
            ])
            ->defaultSort('returned_at', 'desc');
    }
}
