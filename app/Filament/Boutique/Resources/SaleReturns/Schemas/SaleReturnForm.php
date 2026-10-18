<?php

namespace App\Filament\Boutique\Resources\SaleReturns\Schemas;

use App\Models\ProductVariant;
use App\Models\Sale;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SaleReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Venta origen')
                    ->columns(2)
                    ->schema([
                        Select::make('sale_id')
                            ->label('Venta')
                            ->relationship('sale', 'number')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        DatePicker::make('returned_at')
                            ->label('Fecha de devolución')
                            ->default(now())
                            ->required(),
                        TextInput::make('reason')
                            ->label('Motivo')
                            ->columnSpanFull(),
                    ]),
                Section::make('Productos a devolver')
                    ->schema([
                        Repeater::make('items')
                            ->label('Productos devueltos')
                            ->addActionLabel('Agregar producto')
                            ->minItems(1)
                            ->columns(2)
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Producto / variante')
                                    ->options(function (Get $get): array {
                                        $saleId = $get('../../sale_id');

                                        return $saleId ? static::saleVariantOptions((int) $saleId) : [];
                                    })
                                    ->searchable()
                                    ->required(),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Variantes vendidas en la venta origen, para elegir qué devolver.
     *
     * @return array<int, string>
     */
    private static function saleVariantOptions(int $saleId): array
    {
        $sale = Sale::query()->with('items.variant.product')->find($saleId);

        if ($sale === null) {
            return [];
        }

        return $sale->items
            ->mapWithKeys(fn ($item): array => [
                (int) $item->product_variant_id => $item->variant
                    ? static::variantLabel($item->variant)
                    : 'Variante',
            ])
            ->all();
    }

    private static function variantLabel(ProductVariant $variant): string
    {
        $attributes = collect([$variant->size, $variant->color, $variant->model])
            ->filter()
            ->implode(' / ');

        $name = $variant->product?->name ?? 'Variante';

        return $attributes !== '' ? "{$name} — {$attributes}" : $name;
    }
}
