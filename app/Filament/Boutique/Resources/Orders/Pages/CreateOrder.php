<?php

namespace App\Filament\Boutique\Resources\Orders\Pages;

use App\Filament\Boutique\Resources\Orders\OrderResource;
use App\Services\OrderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Auth::id();
        $data['origin'] = 'pedido';
        $data['status'] = 'pending';

        return $data;
    }

    protected function afterCreate(): void
    {
        $order = $this->getRecord();
        $service = app(OrderService::class);

        $service->recomputeTotals($order);

        $order->movements()->create([
            'user_id' => Auth::id(),
            'type' => 'created',
            'description' => 'Pedido creado',
        ]);

        $abono = (float) ($this->data['abono'] ?? 0);
        if ($abono > 0) {
            $service->registerPayment($order, min($abono, (float) $order->total), 'cash');
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
