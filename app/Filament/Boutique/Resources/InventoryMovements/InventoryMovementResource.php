<?php

namespace App\Filament\Boutique\Resources\InventoryMovements;

use App\Filament\Boutique\Resources\InventoryMovements\Pages\ListInventoryMovements;
use App\Filament\Boutique\Resources\InventoryMovements\Pages\ViewInventoryMovement;
use App\Filament\Boutique\Resources\InventoryMovements\Schemas\InventoryMovementInfolist;
use App\Filament\Boutique\Resources\InventoryMovements\Tables\InventoryMovementsTable;
use App\Models\InventoryMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Tu catálogo';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return 'Movimientos de inventario';
    }

    public static function getModelLabel(): string
    {
        return 'movimiento';
    }

    public static function getPluralModelLabel(): string
    {
        return 'movimientos';
    }

    public static function infolist(Schema $schema): Schema
    {
        return InventoryMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryMovementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInventoryMovements::route('/'),
            'view' => ViewInventoryMovement::route('/{record}'),
        ];
    }
}
