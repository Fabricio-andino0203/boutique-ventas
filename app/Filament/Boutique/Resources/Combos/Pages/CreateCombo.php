<?php

namespace App\Filament\Boutique\Resources\Combos\Pages;

use App\Filament\Boutique\Resources\Combos\ComboResource;
use App\Filament\Boutique\Resources\Combos\Schemas\ComboForm;
use App\Services\ComboService;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class CreateCombo extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = ComboResource::class;

    public function getTitle(): string
    {
        return 'Nuevo combo';
    }

    /**
     * @return array<int, Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make('Productos')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->description('Qué agrupa el combo')
                ->schema([ComboForm::itemsRepeater()]),
            Step::make('Nombre y precio')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->description('Cómo se llama y cuánto cuesta')
                ->schema(ComboForm::nameAndPriceFields())
                ->columns(2),
            Step::make('Stock')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->description('Derivado o propio')
                ->schema(ComboForm::stockFields())
                ->columns(2),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(ComboService::class)->create($data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Combo creado';
    }
}
