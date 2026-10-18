<?php

namespace App\Filament\Boutique\Resources\Purchases;

use App\Filament\Boutique\Resources\Purchases\Pages\CreatePurchase;
use App\Filament\Boutique\Resources\Purchases\Pages\ListPurchases;
use App\Filament\Boutique\Resources\Purchases\Schemas\PurchaseForm;
use App\Filament\Boutique\Resources\Purchases\Tables\PurchasesTable;
use App\Models\Purchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Compras';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Compras';
    }

    public static function getModelLabel(): string
    {
        return 'compra';
    }

    public static function getPluralModelLabel(): string
    {
        return 'compras';
    }

    public static function form(Schema $schema): Schema
    {
        return PurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PurchasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
            'create' => CreatePurchase::route('/create'),
        ];
    }
}
