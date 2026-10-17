<?php

namespace App\Filament\Boutique\Resources\Suppliers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre del proveedor')
                    ->required(),
                TextInput::make('contact_name')
                    ->label('Persona de contacto'),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel(),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email(),
                TextInput::make('tax_id')
                    ->label('RTN'),
                TextInput::make('address')
                    ->label('Dirección')
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->label('Observaciones')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
