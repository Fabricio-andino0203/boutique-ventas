<?php

namespace App\Filament\Boutique\Resources\Combos;

use App\Filament\Boutique\Resources\Combos\Pages\CreateCombo;
use App\Filament\Boutique\Resources\Combos\Pages\EditCombo;
use App\Filament\Boutique\Resources\Combos\Pages\ListCombos;
use App\Filament\Boutique\Resources\Combos\Schemas\ComboForm;
use App\Filament\Boutique\Resources\Combos\Tables\CombosTable;
use App\Models\Combo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ComboResource extends Resource
{
    protected static ?string $model = Combo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Tu catálogo';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Combos';
    }

    public static function getModelLabel(): string
    {
        return 'combo';
    }

    public static function getPluralModelLabel(): string
    {
        return 'combos';
    }

    public static function form(Schema $schema): Schema
    {
        return ComboForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CombosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCombos::route('/'),
            'create' => CreateCombo::route('/create'),
            'edit' => EditCombo::route('/{record}/edit'),
        ];
    }
}
