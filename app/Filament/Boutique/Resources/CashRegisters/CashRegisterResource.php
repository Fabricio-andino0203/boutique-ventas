<?php

namespace App\Filament\Boutique\Resources\CashRegisters;

use App\Filament\Boutique\Resources\CashRegisters\Pages\ListCashRegisters;
use App\Filament\Boutique\Resources\CashRegisters\Tables\CashRegistersTable;
use App\Models\CashRegister;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CashRegisterResource extends Resource
{
    protected static ?string $model = CashRegister::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Caja';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Caja';
    }

    public static function getModelLabel(): string
    {
        return 'caja';
    }

    public static function getPluralModelLabel(): string
    {
        return 'cajas';
    }

    public static function table(Table $table): Table
    {
        return CashRegistersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashRegisters::route('/'),
        ];
    }
}
