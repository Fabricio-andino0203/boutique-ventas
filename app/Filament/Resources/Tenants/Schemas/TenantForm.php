<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                TextInput::make('business_name'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('address'),
                TextInput::make('city'),
                TextInput::make('status')
                    ->required()
                    ->default('trial'),
                TextInput::make('currency')
                    ->required()
                    ->default('HNL'),
                TextInput::make('timezone')
                    ->required()
                    ->default('America/Tegucigalpa'),
                TextInput::make('logo_path'),
                TextInput::make('settings'),
                DateTimePicker::make('trial_ends_at'),
            ]);
    }
}
