<?php

namespace App\Filament\Boutique\Resources\Sales\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('N.º')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Consumidor final'),
                TextColumn::make('user.name')
                    ->label('Vendedor')
                    ->toggleable(),
                TextColumn::make('payment_method')
                    ->label('Pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                        default => $state,
                    }),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('HNL')
                    ->sortable(),
            ])
            ->defaultSort('sold_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
