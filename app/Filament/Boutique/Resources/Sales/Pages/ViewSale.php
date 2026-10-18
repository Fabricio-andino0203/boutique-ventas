<?php

namespace App\Filament\Boutique\Resources\Sales\Pages;

use App\Filament\Boutique\Resources\Sales\SaleResource;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recibo')
                ->label('Imprimir recibo')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(fn (Sale $record): string => route('boutique.sales.receipt', $record))
                ->openUrlInNewTab(),
        ];
    }
}
