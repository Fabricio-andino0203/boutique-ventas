<?php

namespace App\Filament\Boutique\Resources\Sales\Pages;

use App\Filament\Boutique\Resources\Sales\SaleResource;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
