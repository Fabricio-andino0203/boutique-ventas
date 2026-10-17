<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, $context) {
                        if ($context === 'create' && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Identificador (slug)')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->label('Precio')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('L'),
                Select::make('currency')
                    ->label('Moneda')
                    ->options(['HNL' => 'Lempiras (HNL)', 'USD' => 'Dólares (USD)'])
                    ->default('HNL')
                    ->required(),
                TextInput::make('duration_days')
                    ->label('Duración (días)')
                    ->required()
                    ->numeric()
                    ->default(30),
                Select::make('features')
                    ->label('Funcionalidades incluidas')
                    ->relationship('features', 'name')
                    ->multiple()
                    ->preload()
                    ->columnSpanFull(),
                KeyValue::make('limits')
                    ->label('Límites del plan')
                    ->keyLabel('Clave (ej. max_products)')
                    ->valueLabel('Valor')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
                Toggle::make('is_public')
                    ->label('Visible públicamente')
                    ->default(true),
                TextInput::make('sort_order')
                    ->label('Orden')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
