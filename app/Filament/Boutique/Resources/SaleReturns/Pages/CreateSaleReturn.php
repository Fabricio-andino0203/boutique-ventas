<?php

namespace App\Filament\Boutique\Resources\SaleReturns\Pages;

use App\Actions\RegisterReturn;
use App\Filament\Boutique\Resources\SaleReturns\SaleReturnResource;
use App\Models\Sale;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSaleReturn extends CreateRecord
{
    protected static string $resource = SaleReturnResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Registra la devolución mediante la acción transaccional: reingresa el
     * stock de cada variante y deja el movimiento de inventario.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Sale $sale */
        $sale = Sale::query()->findOrFail($data['sale_id']);

        $items = collect($data['items'] ?? [])
            ->map(fn (array $row): array => [
                'variant_id' => (int) $row['product_variant_id'],
                'quantity' => (int) $row['quantity'],
            ])
            ->all();

        return app(RegisterReturn::class)->execute($sale, $items, [
            'returned_at' => $data['returned_at'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);
    }
}
