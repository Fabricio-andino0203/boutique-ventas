<?php

namespace App\Filament\Boutique\Resources\Users;

use App\Filament\Boutique\Resources\Users\Pages\CreateUser;
use App\Filament\Boutique\Resources\Users\Pages\EditUser;
use App\Filament\Boutique\Resources\Users\Pages\ListUsers;
use App\Filament\Boutique\Resources\Users\Schemas\UserForm;
use App\Filament\Boutique\Resources\Users\Tables\UsersTable;
use App\Models\User;
use App\Support\CurrentTenant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    public static function getNavigationLabel(): string
    {
        return 'Vendedores';
    }

    public static function getModelLabel(): string
    {
        return 'vendedor';
    }

    public static function getPluralModelLabel(): string
    {
        return 'vendedores';
    }

    /**
     * Aísla el recurso por comercio sin tocar el modelo: solo usuarios del
     * comercio activo y nunca super-admins del backoffice.
     */
    public static function getEloquentQuery(): Builder
    {
        return User::query()
            ->where('tenant_id', app(CurrentTenant::class)->id())
            ->where('is_super_admin', false);
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
