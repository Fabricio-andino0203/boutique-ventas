<?php

namespace App\Filament\Boutique\Resources\CashRegisters\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashRegistersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['openedBy', 'closedBy']))
            ->columns([
                TextColumn::make('opened_at')
                    ->label('Apertura')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('openedBy.name')
                    ->label('Abrió')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'open' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Abierta',
                        'closed' => 'Cerrada',
                        default => $state,
                    }),
                TextColumn::make('opening_balance')
                    ->label('Apertura (L)')
                    ->money('HNL')
                    ->sortable(),
                TextColumn::make('expected_balance')
                    ->label('Esperado')
                    ->money('HNL')
                    ->placeholder('—'),
                TextColumn::make('closing_balance')
                    ->label('Contado')
                    ->money('HNL')
                    ->placeholder('—'),
                TextColumn::make('difference')
                    ->label('Diferencia')
                    ->money('HNL')
                    ->placeholder('—')
                    ->color(fn ($state): string => $state < 0 ? 'danger' : 'success'),
            ])
            ->defaultSort('opened_at', 'desc');
    }
}
