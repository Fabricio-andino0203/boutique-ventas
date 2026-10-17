<?php

namespace App\Filament\Resources\Payments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->label('Comercio')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('plan_id')
                    ->label('Plan')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('subscription_id')
                    ->label('Suscripción')
                    ->relationship('subscription', 'id'),
                TextInput::make('amount')
                    ->label('Monto')
                    ->required()
                    ->numeric()
                    ->prefix('L'),
                Select::make('currency')
                    ->label('Moneda')
                    ->options(['HNL' => 'Lempiras (HNL)', 'USD' => 'Dólares (USD)'])
                    ->default('HNL')
                    ->required(),
                Select::make('method')
                    ->label('Método de pago')
                    ->options([
                        'cash' => 'Efectivo',
                        'bank_transfer' => 'Transferencia bancaria',
                    ])
                    ->default('cash')
                    ->required(),
                TextInput::make('reference')
                    ->label('Referencia / N.º de transferencia'),
                DateTimePicker::make('paid_at')
                    ->label('Fecha de pago')
                    ->default(now()),
                Textarea::make('notes')
                    ->label('Observaciones')
                    ->columnSpanFull(),
            ]);
    }
}
