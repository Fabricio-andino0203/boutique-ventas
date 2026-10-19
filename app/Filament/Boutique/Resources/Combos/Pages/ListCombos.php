<?php

namespace App\Filament\Boutique\Resources\Combos\Pages;

use App\Filament\Boutique\Resources\Combos\ComboResource;
use App\Filament\Boutique\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListCombos extends ListRecords
{
    protected static string $resource = ComboResource::class;

    public function getTitle(): string
    {
        return 'Combos';
    }

    public function getSubheading(): ?string
    {
        return 'Productos que agrupan a otros y se venden como uno solo.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verProductos')
                ->label('Ver productos')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('gray')
                ->url(ProductResource::getUrl('index')),
            CreateAction::make()
                ->label('Crear combo')
                ->icon(Heroicon::OutlinedPlus),
        ];
    }
}
