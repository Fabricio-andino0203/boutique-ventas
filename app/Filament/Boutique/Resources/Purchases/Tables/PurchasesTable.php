<?php

namespace App\Filament\Boutique\Resources\Purchases\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['supplier', 'user']))
            ->columns([
                TextColumn::make('number')
                    ->label('N.º')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->placeholder('Sin proveedor')
                    ->searchable(),
                TextColumn::make('purchased_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('HNL')
                    ->sortable(),
            ])
            ->defaultSort('purchased_at', 'desc');
    }
}
