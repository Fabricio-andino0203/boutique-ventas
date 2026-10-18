<?php

namespace App\Filament\Boutique\Resources\Customers;

use App\Filament\Boutique\Resources\Customers\Pages\CreateCustomer;
use App\Filament\Boutique\Resources\Customers\Pages\EditCustomer;
use App\Filament\Boutique\Resources\Customers\Pages\ListCustomers;
use App\Filament\Boutique\Resources\Customers\Schemas\CustomerForm;
use App\Filament\Boutique\Resources\Customers\Tables\CustomersTable;
use App\Models\Customer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Clientes';
    }

    public static function getModelLabel(): string
    {
        return 'cliente';
    }

    public static function getPluralModelLabel(): string
    {
        return 'clientes';
    }

    public static function form(Schema $schema): Schema
    {
        return CustomerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomersTable::configure($table);
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
            'index' => ListCustomers::route('/'),
            'create' => CreateCustomer::route('/create'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
