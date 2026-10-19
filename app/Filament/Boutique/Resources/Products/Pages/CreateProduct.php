<?php

namespace App\Filament\Boutique\Resources\Products\Pages;

use App\Filament\Boutique\Resources\Products\ProductResource;
use App\Filament\Boutique\Resources\Products\Schemas\ProductForm;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
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
                        ->options(['simple' => 'Simple', 'variable' => 'Variable'])
                        ->icons(['simple' => Heroicon::OutlinedCube, 'variable' => Heroicon::OutlinedSquares2x2])
                        ->inline()
                        ->default('simple')
                        ->live()
                        ->columnSpanFull(),
                    ...ProductForm::basicFields(),
                    ProductForm::barcodeField()
                        ->visible(fn (Get $get): bool => $get('type') === 'simple'),
                ])
                ->columns(2),

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

            Step::make('Precio e inventario')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->schema([
                    // Producto simple: precio, costo, utilidad, stock, etc.
                    Group::make()
                        ->visible(fn (Get $get): bool => $get('type') === 'simple')
                        ->schema([
                            ...ProductForm::pricingFields(),
                            TextInput::make('simple_stock')
                                ->label('Stock inicial')
                                ->numeric()
                                ->default(0)
                                ->dehydrated(false),
                        ])
                        ->columns(2),
                    // Producto variable: impuestos/descuentos a nivel de producto.
                    Group::make()
                        ->visible(fn (Get $get): bool => $get('type') === 'variable')
                        ->schema([
                            Placeholder::make('variable_nota')
                                ->label('')
                                ->content('Los precios, costos y existencias se definen en cada variante (paso anterior). Aquí configurás impuestos y descuentos del producto.')
                                ->columnSpanFull(),
                            ...self::productLevelTaxFields(),
                        ])
                        ->columns(2),
                ]),

            Step::make('Imágenes')
                ->icon(Heroicon::OutlinedPhoto)
                ->schema([
                    ProductForm::imageField(),
                ]),
        ];
    }

    /**
     * Impuestos/descuentos a nivel de producto (para producto variable).
     *
     * @return array<int, Component>
     */
    protected static function productLevelTaxFields(): array
    {
        $all = ProductForm::pricingFields();

        // Reutilizamos solo los campos de impuesto/descuento (los últimos tres).
        return array_values(array_filter($all, function ($component): bool {
            $name = method_exists($component, 'getName') ? $component->getName() : null;

            return in_array($name, ['allow_discount', 'tax_type', 'tax_rate'], true);
        }));
    }

    protected function afterCreate(): void
    {
        $product = $this->getRecord();

        if (($product->type ?? 'simple') === 'simple') {
            ProductForm::syncSimpleVariant($product, $this->data);
        }
    }
}
