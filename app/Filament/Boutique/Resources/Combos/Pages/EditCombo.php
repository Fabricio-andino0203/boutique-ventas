<?php

namespace App\Filament\Boutique\Resources\Combos\Pages;

use App\Filament\Boutique\Resources\Combos\ComboResource;
use App\Models\Combo;
use App\Services\ComboService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCombo extends EditRecord
{
    protected static string $resource = ComboResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (Combo $record): void {
                    // Elimina también el producto/variante de respaldo del combo.
                    $record->product?->delete();
                }),
        ];
    }

    /**
     * Carga los componentes del combo en el repeater del formulario.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Combo $combo */
        $combo = $this->getRecord();

        $data['items'] = $combo->items->map(fn ($item): array => [
            'product_id' => $item->product_id,
            'choose_at_sale' => $item->product_variant_id === null,
            'product_variant_id' => $item->product_variant_id,
            'eligible_variant_ids' => $item->eligible_variant_ids ?? [],
            'quantity' => $item->quantity,
        ])->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Combo $record */
        return app(ComboService::class)->update($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Combo actualizado';
    }
}
