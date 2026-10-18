<?php

namespace App\Filament\Boutique\Resources\Sales;

use App\Filament\Boutique\Resources\Sales\Pages\ListSales;
use App\Filament\Boutique\Resources\Sales\Pages\ViewSale;
use App\Filament\Boutique\Resources\Sales\Schemas\SaleInfolist;
use App\Filament\Boutique\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'Operación diaria';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Historial de ventas';
    }

    public static function getModelLabel(): string
    {
        return 'venta';
    }

    public static function getPluralModelLabel(): string
    {
        return 'ventas';
    }

    public static function infolist(Schema $schema): Schema
    {
        return SaleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SalesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSales::route('/'),
            'view' => ViewSale::route('/{record}'),
        ];
    }
}
