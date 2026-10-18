<?php

namespace App\Filament\Boutique\Pages;

use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Operación diaria';

    protected static ?int $navigationSort = -5;

    protected static ?string $navigationLabel = 'Inicio';

    public function getTitle(): string
    {
        return 'Inicio';
    }

    public function getHeading(): string
    {
        return 'Inicio';
    }
}
