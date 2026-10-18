<?php

namespace App\Filament\Boutique\Resources\Purchases\Schemas;

use App\Models\ProductVariant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la compra')
                    ->columns(2)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Proveedor')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Sin proveedor'),
                        DatePicker::make('purchased_at')
                            ->label('Fecha de compra')
                            ->default(now())
                            ->required(),
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->columnSpanFull(),
                    ]),
                Section::make('Productos')
                    ->schema([
                        Repeater::make('items')
                            ->label('Productos a ingresar')
                            ->addActionLabel('Agregar producto')
                            ->minItems(1)
                            ->columns(3)
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Producto / variante')
                                    ->options(fn (): array => static::variantOptions())
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required(),
                                TextInput::make('unit_cost')
                                    ->label('Costo unitario')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('L')
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * Variantes del comercio activo, etiquetadas con producto y atributos.
     *
     * @return array<int, string>
     */
    private static function variantOptions(): array
    {
        return ProductVariant::query()
            ->with('product')
            ->get()
            ->mapWithKeys(fn (ProductVariant $variant): array => [
                $variant->id => static::variantLabel($variant),
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
