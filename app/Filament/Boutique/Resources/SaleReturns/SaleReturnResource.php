<?php

namespace App\Filament\Boutique\Resources\SaleReturns;

use App\Filament\Boutique\Resources\SaleReturns\Pages\CreateSaleReturn;
use App\Filament\Boutique\Resources\SaleReturns\Pages\ListSaleReturns;
use App\Filament\Boutique\Resources\SaleReturns\Schemas\SaleReturnForm;
use App\Filament\Boutique\Resources\SaleReturns\Tables\SaleReturnsTable;
use App\Models\SaleReturn;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SaleReturnResource extends Resource
{
    protected static ?string $model = SaleReturn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Devoluciones';
    }

    public static function getModelLabel(): string
    {
        return 'devolución';
    }

    public static function getPluralModelLabel(): string
    {
        return 'devoluciones';
    }

    public static function form(Schema $schema): Schema
    {
        return SaleReturnForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SaleReturnsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSaleReturns::route('/'),
            'create' => CreateSaleReturn::route('/create'),
        ];
    }
}
