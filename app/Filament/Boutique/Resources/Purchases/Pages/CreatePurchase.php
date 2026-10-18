<?php

namespace App\Filament\Boutique\Resources\Purchases\Pages;

use App\Actions\RegisterPurchase;
use App\Filament\Boutique\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePurchase extends CreateRecord
{
    protected static string $resource = PurchaseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Registra la compra mediante la acción transaccional: incrementa el stock
     * de cada variante y deja el movimiento de inventario.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $items = collect($data['items'] ?? [])
            ->map(fn (array $row): array => [
                'variant_id' => (int) $row['product_variant_id'],
                'quantity' => (int) $row['quantity'],
                'unit_cost' => (float) $row['unit_cost'],
            ])
            ->all();

        return app(RegisterPurchase::class)->execute($items, [
            'supplier_id' => $data['supplier_id'] ?? null,
            'purchased_at' => $data['purchased_at'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
