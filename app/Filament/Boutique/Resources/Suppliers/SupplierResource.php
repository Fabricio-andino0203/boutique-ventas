<?php

namespace App\Filament\Boutique\Resources\Suppliers;

use App\Filament\Boutique\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Boutique\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Boutique\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Boutique\Resources\Suppliers\Schemas\SupplierForm;
use App\Filament\Boutique\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Supplier;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupplierResource extends Resource
{
    protected static ?string $model = Supplier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|\UnitEnum|null $navigationGroup = 'Contactos';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Proveedores';
    }

    public static function getModelLabel(): string
    {
        return 'proveedor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'proveedores';
    }

    public static function form(Schema $schema): Schema
    {
        return SupplierForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SuppliersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSuppliers::route('/'),
            'create' => CreateSupplier::route('/create'),
            'edit' => EditSupplier::route('/{record}/edit'),
        ];
    }
}
