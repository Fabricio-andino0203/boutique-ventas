<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre del comercio')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, ?string $old, $context) {
                        if ($context === 'create' && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Identificador (slug)')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText('Único por comercio. Se genera del nombre.'),
                TextInput::make('business_name')
                    ->label('Razón social'),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email(),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel(),
                TextInput::make('city')
                    ->label('Ciudad'),
                TextInput::make('address')
                    ->label('Dirección')
                    ->columnSpanFull(),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'trial' => 'Prueba',
                        'active' => 'Activo',
                        'suspended' => 'Suspendido',
                        'inactive' => 'Inactivo',
                    ])
                    ->default('trial')
                    ->required(),
                Select::make('currency')
                    ->label('Moneda')
                    ->options(['HNL' => 'Lempiras (HNL)', 'USD' => 'Dólares (USD)'])
                    ->default('HNL')
                    ->required(),
                TextInput::make('timezone')
                    ->label('Zona horaria')
                    ->default('America/Tegucigalpa')
                    ->required(),
                DateTimePicker::make('trial_ends_at')
                    ->label('Fin del periodo de prueba'),
            ]);
    }
}
