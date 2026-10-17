<?php

namespace App\Filament\Boutique\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre del producto')
                    ->required()
                    ->columnSpanFull(),
                Select::make('category_id')
                    ->label('Categoría')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required(),
                    ]),
                TextInput::make('brand')
                    ->label('Marca'),
                Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
                TextInput::make('default_price')
                    ->label('Precio de venta (referencia)')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('L'),
                TextInput::make('default_cost')
                    ->label('Costo (referencia)')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('L'),
                FileUpload::make('image_path')
                    ->label('Imagen')
                    ->image()
                    ->directory('products')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true),
            ]);
    }
}
