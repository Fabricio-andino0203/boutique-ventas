<?php

namespace App\Filament\Boutique\Resources\Customers\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                TextInput::make('phone')
                    ->label('Teléfono')
                    ->tel(),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email(),
                TextInput::make('identity_document')
                    ->label('Identidad / RTN'),
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
