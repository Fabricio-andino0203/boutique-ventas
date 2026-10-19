<?php

namespace App\Filament\Boutique\Resources\Orders\Schemas;

use App\Models\ProductVariant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente')
                    ->schema([
                        Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Consumidor final')
                            ->createOptionForm([
                                TextInput::make('name')->label('Nombre o razón social')->required()->maxLength(255),
                                TextInput::make('identity_document')->label('Número de documento')->maxLength(50),
                                TextInput::make('phone')->label('Teléfono')->maxLength(50),
                                TextInput::make('address')->label('Dirección')->maxLength(255),
                            ]),
                    ]),

                Section::make('Productos')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Producto / variante')
                                    ->options(fn (): array => self::variantOptions())
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set): void {
                                        $variant = ProductVariant::with('product')->find($state);
                                        if ($variant) {
                                            $set('unit_price', (float) $variant->price);
                                            $set('name', $variant->name ?: trim(($variant->product?->name ?? '').' '.($variant->size ?? '').' '.($variant->color ?? '')));
                                        }
                                    })
                                    ->columnSpan(2),
                                TextInput::make('name')->label('Nombre')->hidden()->dehydrated(),
                                TextInput::make('quantity')->label('Cantidad')->numeric()->default(1)->minValue(1)->required(),
                                TextInput::make('unit_price')->label('Precio unit.')->numeric()->default(0)->prefix('L')->required(),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Agregar producto')
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Producto'),
                    ]),

                Section::make('Entrega')
                    ->schema([
                        ToggleButtons::make('type')
                            ->label('Tipo de entrega')
                            ->options(['recogida' => 'Recogida', 'domicilio' => 'Domicilio'])
                            ->icons(['recogida' => Heroicon::OutlinedBuildingStorefront, 'domicilio' => Heroicon::OutlinedTruck])
                            ->inline()
                            ->default('recogida')
                            ->live()
                            ->columnSpanFull(),
                        TextInput::make('delivery_address')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->required(fn (Get $get): bool => $get('type') === 'domicilio')
                            ->visible(fn (Get $get): bool => $get('type') === 'domicilio')
                            ->columnSpanFull(),
                        TextInput::make('recipient')
                            ->label('Destinatario')
                            ->maxLength(255)
                            ->visible(fn (Get $get): bool => $get('type') === 'domicilio'),
                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->maxLength(50)
                            ->visible(fn (Get $get): bool => $get('type') === 'domicilio'),
                    ])
                    ->columns(2),

                Section::make('Descuento y abono')
                    ->schema([
                        ToggleButtons::make('discount_type')
                            ->label('Tipo de descuento')
                            ->options(['fixed' => 'Fijo (L)', 'percent' => 'Porcentaje (%)'])
                            ->inline()
                            ->default('fixed'),
                        TextInput::make('discount_value')->label('Descuento')->numeric()->default(0),
                        TextInput::make('abono')
                            ->label('Pago anticipado / abono (efectivo)')
                            ->numeric()
                            ->default(0)
                            ->prefix('L')
                            ->dehydrated(false)
                            ->helperText('Opcional. Si cubre el total, el pedido queda pagado y genera la venta.'),
                        DateTimePicker::make('estimated_at')->label('Fecha estimada')->seconds(false),
                    ])
                    ->columns(2),

                Section::make('Notas')
                    ->schema([
                        Textarea::make('notes')->label('Notas')->rows(2)->maxLength(1000)->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Opciones de variantes para elegir en el pedido (producto + variante + precio).
     *
     * @return array<int, string>
     */
    public static function variantOptions(): array
    {
        return ProductVariant::query()
            ->with('product')
            ->get()
            ->mapWithKeys(function (ProductVariant $variant): array {
                $label = trim(($variant->product?->name ?? 'Producto').' '.($variant->name && $variant->name !== $variant->product?->name
                    ? '· '.str_replace(($variant->product?->name ?? '').' - ', '', $variant->name)
                    : trim(($variant->size ?? '').' '.($variant->color ?? ''))));

                return [$variant->id => $label.'  — L '.number_format((float) $variant->price, 2)];
            })
            ->all();
    }
}
