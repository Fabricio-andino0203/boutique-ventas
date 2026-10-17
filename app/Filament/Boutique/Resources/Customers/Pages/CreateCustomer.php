<?php

namespace App\Filament\Boutique\Resources\Customers\Pages;

use App\Filament\Boutique\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
