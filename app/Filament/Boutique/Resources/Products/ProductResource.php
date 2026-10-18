<?php

namespace App\Filament\Boutique\Resources\Products;

use App\Filament\Boutique\Resources\Products\Pages\CreateProduct;
use App\Filament\Boutique\Resources\Products\Pages\EditProduct;
use App\Filament\Boutique\Resources\Products\Pages\ListProducts;
use App\Filament\Boutique\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Boutique\Resources\Products\Schemas\ProductForm;
use App\Filament\Boutique\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|\UnitEnum|null $navigationGroup = 'Tu catálogo';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Productos';
    }

    public static function getModelLabel(): string
    {
        return 'producto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'productos';
    }

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
