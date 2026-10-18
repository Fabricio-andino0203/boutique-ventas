<?php

namespace App\Filament\Boutique\Resources\InventoryMovements\Schemas;

use App\Filament\Boutique\Resources\InventoryMovements\Tables\InventoryMovementsTable;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('variant.product.name')
                    ->label('Producto')
                    ->placeholder('—'),
                TextEntry::make('variant.sku')
                    ->label('SKU')
                    ->placeholder('—'),
                TextEntry::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => InventoryMovementsTable::typeLabel($state)),
                TextEntry::make('quantity')
                    ->label('Cantidad')
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->weight('bold'),
                TextEntry::make('balance_after')
                    ->label('Existencia resultante')
                    ->placeholder('—'),
                TextEntry::make('source_type')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => InventoryMovementsTable::sourceLabel($state))
                    ->placeholder('—'),
                TextEntry::make('user.name')
                    ->label('Usuario')
                    ->placeholder('—'),
                TextEntry::make('notes')
                    ->label('Notas')
                    ->columnSpanFull()
                    ->placeholder('—'),
            ]);
    }
}
