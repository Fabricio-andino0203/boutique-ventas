<?php

namespace App\Filament\Boutique\Resources\Orders;

use App\Filament\Boutique\Resources\Orders\Pages\CreateOrder;
use App\Filament\Boutique\Resources\Orders\Pages\ListOrders;
use App\Filament\Boutique\Resources\Orders\Pages\ViewOrder;
use App\Filament\Boutique\Resources\Orders\Schemas\OrderForm;
use App\Filament\Boutique\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Operación diaria';

    protected static ?int $navigationSort = -3;

    public static function getNavigationLabel(): string
    {
        return 'Pedidos';
    }

    public static function getModelLabel(): string
    {
        return 'pedido';
    }

    public static function getPluralModelLabel(): string
    {
        return 'pedidos';
    }

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
