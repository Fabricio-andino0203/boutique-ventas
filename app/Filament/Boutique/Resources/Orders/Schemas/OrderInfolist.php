<?php

namespace App\Filament\Boutique\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pedido')
                    ->schema([
                        TextEntry::make('number')->label('N.º'),
                        TextEntry::make('status')->label('Estado')->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'completed' => 'Completado', 'cancelled' => 'Cancelado', default => 'Pendiente',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'completed' => 'success', 'cancelled' => 'danger', default => 'warning',
                            }),
                        TextEntry::make('payment_status')->label('Pago')->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'paid' => 'Pagado', 'partial' => 'Parcial', default => 'Sin pagar',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'paid' => 'success', 'partial' => 'warning', default => 'danger',
                            }),
                        TextEntry::make('type')->label('Entrega')
                            ->formatStateUsing(fn (string $state): string => $state === 'domicilio' ? 'Domicilio' : 'Recogida'),
                        TextEntry::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(3),

                Section::make('Cliente')
                    ->schema([
                        TextEntry::make('customer.name')->label('Cliente')->placeholder('Consumidor final'),
                        TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Entrega a domicilio')
                    ->visible(fn ($record): bool => $record->type === 'domicilio')
                    ->schema([
                        TextEntry::make('delivery_address')->label('Dirección')->placeholder('—'),
                        TextEntry::make('recipient')->label('Destinatario')->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Productos')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')->label('Producto'),
                                TextEntry::make('quantity')->label('Cant.'),
                                TextEntry::make('unit_price')->label('Precio')->money('HNL'),
                                TextEntry::make('subtotal')->label('Subtotal')->money('HNL'),
                            ])
                            ->columns(4),
                    ]),

                Section::make('Resumen financiero')
                    ->schema([
                        TextEntry::make('subtotal')->label('Subtotal')->money('HNL'),
                        TextEntry::make('discount_amount')->label('Descuento')->money('HNL'),
                        TextEntry::make('tax')->label('Impuesto')->money('HNL'),
                        TextEntry::make('total')->label('Total')->money('HNL')->weight('bold'),
                        TextEntry::make('paid')->label('Pagado')->money('HNL')->color('success'),
                        TextEntry::make('due')->label('Deuda pendiente')->state(fn ($record): float => $record->due)->money('HNL')->color('danger'),
                    ])
                    ->columns(3),

                Section::make('Historial de movimientos')
                    ->schema([
                        RepeatableEntry::make('movements')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('description')->hiddenLabel(),
                                TextEntry::make('created_at')->hiddenLabel()->dateTime('d/m/Y H:i'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
