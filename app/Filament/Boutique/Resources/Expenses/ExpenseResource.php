<?php

namespace App\Filament\Boutique\Resources\Expenses;

use App\Filament\Boutique\Resources\Expenses\Pages\CreateExpense;
use App\Filament\Boutique\Resources\Expenses\Pages\EditExpense;
use App\Filament\Boutique\Resources\Expenses\Pages\ListExpenses;
use App\Filament\Boutique\Resources\Expenses\Schemas\ExpenseForm;
use App\Filament\Boutique\Resources\Expenses\Tables\ExpensesTable;
use App\Models\Expense;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = 'Finanzas';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return 'Gastos';
    }

    public static function getModelLabel(): string
    {
        return 'gasto';
    }

    public static function getPluralModelLabel(): string
    {
        return 'gastos';
    }

    public static function form(Schema $schema): Schema
    {
        return ExpenseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpensesTable::configure($table);
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
            'index' => ListExpenses::route('/'),
            'create' => CreateExpense::route('/create'),
            'edit' => EditExpense::route('/{record}/edit'),
        ];
    }
}
