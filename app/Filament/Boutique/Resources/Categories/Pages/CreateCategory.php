<?php

namespace App\Filament\Boutique\Resources\Categories\Pages;

use App\Filament\Boutique\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;

    /**
     * Tras crear la categoría, vamos a su pantalla de edición para poder
     * agregarle subcategorías de inmediato (el gestor de subcategorías vive
     * en la edición, ya que necesita una categoría madre existente).
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
