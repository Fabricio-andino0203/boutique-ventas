<?php

namespace App\Filament\Boutique\Resources\InventoryMovements\Pages;

use App\Filament\Boutique\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Resources\Pages\ListRecords;

class ListInventoryMovements extends ListRecords
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
