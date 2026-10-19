<?php

namespace App\Filament\Boutique\Resources\Products\Pages;

use App\Filament\Boutique\Resources\Products\ProductResource;
use App\Filament\Boutique\Resources\Products\Schemas\ProductForm;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Icons\Heroicon;

class CreateProduct extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = ProductResource::class;

    /**
     * Tras crear el producto, vamos a su edición (para gestionar variantes,
     * imágenes y más detalles).
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * @return array<int, Step>
     */
    protected function getSteps(): array
    {
        return [
            Step::make('Información básica')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->schema([
                    ToggleButtons::make('type')
                        ->label('Tipo de producto')
                        ->options(ProductForm::typeOptions())
                        ->icons([
                            'simple' => Heroicon::OutlinedCube,
                            'variable' => Heroicon::OutlinedSquares2x2,
                            'custom' => Heroicon::OutlinedCurrencyDollar,
                        ])
                        ->inline()
                        ->default('simple')
                        ->required()
                        ->live()
                        ->helperText('Simple: un precio y un inventario. Variable: tallas/colores con su propio precio y stock. Precio personalizado: el cajero ingresa el precio en cada venta.')
                        ->columnSpanFull(),
                    ...ProductForm::basicFields(),
                ])
                ->columns(2),

            Step::make('Precio e inventario')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->schema([
                    Placeholder::make('variable_nota')
                        ->label('')
                        ->content('Estos valores serán los predeterminados para todas las variantes. Podrás ajustarlos individualmente en cada variante.')
                        ->visible(fn (Get $get): bool => $get('type') === 'variable')
                        ->columnSpanFull(),
                    Placeholder::make('custom_nota')
                        ->label('')
                        ->content('En "Precio personalizado" el cajero ingresa el precio en cada venta; no se define aquí.')
                        ->visible(fn (Get $get): bool => $get('type') === 'custom')
                        ->columnSpanFull(),
                    ...ProductForm::pricingFields(),
                    TextInput::make('simple_stock')
                        ->label('Stock inicial')
                        ->numeric()
                        ->default(0)
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => $get('type') !== 'variable'),
                    ...ProductForm::optionsAndTaxFields(),
                ])
                ->columns(2),

            Step::make('Imágenes')
                ->icon(Heroicon::OutlinedPhoto)
                ->schema(ProductForm::imageFields()),

            Step::make('Variantes')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->visible(fn (Get $get): bool => $get('type') === 'variable')
                ->schema([
                    Repeater::make('variants')
                        ->label('Variantes del producto')
                        ->relationship()
                        ->schema(ProductForm::variantFields())
                        ->columns(3)
                        ->defaultItems(1)
                        ->minItems(1)
                        ->collapsible()
                        ->itemLabel(fn (array $state): string => trim(($state['color'] ?? '').' '.($state['size'] ?? '').' '.($state['model'] ?? '')) ?: 'Variante')
                        ->addActionLabel('Agregar variante'),
                ]),
        ];
    }

    protected function afterCreate(): void
    {
        $product = $this->getRecord();

        // Simple y "precio personalizado" tienen una única variante implícita.
        if (in_array($product->type ?? 'simple', ['simple', 'custom'], true)) {
            ProductForm::syncSimpleVariant($product, $this->data);
        }
    }
}
