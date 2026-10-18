<?php

namespace App\Filament\Boutique\Resources\Sales\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('number')
                    ->label('Número'),
                TextEntry::make('sold_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('customer.name')
                    ->label('Cliente')
                    ->placeholder('Consumidor final'),
                TextEntry::make('user.name')
                    ->label('Vendedor')
                    ->placeholder('—'),
                TextEntry::make('payment_method')
                    ->label('Método de pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                        default => $state,
                    }),
                TextEntry::make('subtotal')
                    ->label('Subtotal')
                    ->money('HNL'),
                TextEntry::make('discount')
                    ->label('Descuento')
                    ->money('HNL'),
                TextEntry::make('total')
                    ->label('Total')
                    ->money('HNL')
                    ->weight('bold'),
                TextEntry::make('amount_paid')
                    ->label('Monto recibido')
                    ->money('HNL'),
                TextEntry::make('change')
                    ->label('Cambio')
                    ->money('HNL'),
                RepeatableEntry::make('items')
                    ->label('Productos vendidos')
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('variant.product.name')
                            ->label('Producto'),
                        TextEntry::make('quantity')
                            ->label('Cantidad'),
                        TextEntry::make('unit_price')
                            ->label('Precio')
                            ->money('HNL'),
                        TextEntry::make('subtotal')
                            ->label('Subtotal')
                            ->money('HNL'),
                    ]),
            ]);
    }
}
