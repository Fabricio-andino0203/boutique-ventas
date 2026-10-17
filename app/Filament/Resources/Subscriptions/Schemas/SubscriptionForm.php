<?php

namespace App\Filament\Resources\Subscriptions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubscriptionForm
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
                    ->preload()
                    ->required(),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'active' => 'Activa',
                        'suspended' => 'Suspendida',
                        'expired' => 'Vencida',
                        'cancelled' => 'Cancelada',
                    ])
                    ->default('pending')
                    ->required(),
                TextInput::make('price')
                    ->label('Precio contratado')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('L'),
                DateTimePicker::make('starts_at')
                    ->label('Inicio'),
                DateTimePicker::make('ends_at')
                    ->label('Vencimiento'),
                DateTimePicker::make('trial_ends_at')
                    ->label('Fin del periodo de prueba'),
                Textarea::make('notes')
                    ->label('Observaciones')
                    ->columnSpanFull(),
            ]);
    }
}
