<?php

namespace App\Filament\Boutique\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('description')
                    ->label('Concepto')
                    ->required()
                    ->maxLength(255),
                TextInput::make('category')
                    ->label('Categoría')
                    ->placeholder('Alquiler, servicios, sueldos…')
                    ->maxLength(255),
                TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->prefix('L')
                    ->minValue(0)
                    ->required(),
                Select::make('payment_method')
                    ->label('Método de pago')
                    ->options([
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'transfer' => 'Transferencia',
                    ])
                    ->default('cash')
                    ->required(),
                DatePicker::make('spent_at')
                    ->label('Fecha')
                    ->default(now())
                    ->required(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
