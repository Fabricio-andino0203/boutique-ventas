<?php

namespace App\Filament\Boutique\Resources\Sales\Pages;

use App\Filament\Boutique\Resources\Sales\SaleResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
