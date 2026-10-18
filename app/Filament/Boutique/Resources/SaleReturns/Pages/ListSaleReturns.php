<?php

namespace App\Filament\Boutique\Resources\SaleReturns\Pages;

use App\Filament\Boutique\Resources\SaleReturns\SaleReturnResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSaleReturns extends ListRecords
{
    protected static string $resource = SaleReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva devolución'),
        ];
    }
}
