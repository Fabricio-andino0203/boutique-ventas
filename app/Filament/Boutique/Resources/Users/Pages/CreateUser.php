<?php

namespace App\Filament\Boutique\Resources\Users\Pages;

use App\Filament\Boutique\Resources\Users\UserResource;
use App\Support\CurrentTenant;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Fuerza la pertenencia al comercio activo y evita crear super-admins.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = app(CurrentTenant::class)->id();
        $data['is_super_admin'] = false;

        return $data;
    }
}
