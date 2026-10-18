<?php

namespace App\Filament\Boutique\Resources\InventoryMovements\Pages;

use App\Filament\Boutique\Resources\InventoryMovements\InventoryMovementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryMovement extends ViewRecord
{
    protected static string $resource = InventoryMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
