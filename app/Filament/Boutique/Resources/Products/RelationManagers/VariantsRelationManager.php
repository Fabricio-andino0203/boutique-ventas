<?php

namespace App\Filament\Boutique\Resources\Products\RelationManagers;

use App\Support\Barcode;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variantes (tallas, colores, modelos)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('size')
                    ->label('Talla'),
                TextInput::make('color')
                    ->label('Color'),
                TextInput::make('model')
                    ->label('Modelo'),
                TextInput::make('sku')
                    ->label('SKU / Código'),
                TextInput::make('barcode')
                    ->label('Código de barras')
                    ->suffixAction(
                        Action::make('generarCodigo')
                            ->icon(Heroicon::OutlinedSparkles)
                            ->tooltip('Generar código')
                            ->action(fn (Set $set) => $set('barcode', Barcode::generateEan13()))
                    ),
                TextInput::make('price')
                    ->label('Precio de venta')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('L'),
                TextInput::make('cost')
                    ->label('Costo')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('L'),
                TextInput::make('wholesale_price')
                    ->label('Precio mayorista')
                    ->numeric()
                    ->prefix('L'),
                FileUpload::make('image_path')
                    ->label('Imagen de la variante')
                    ->image()
                    ->disk(config('filesystems.media'))
                    ->directory('variants')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1000)
                    ->imageResizeTargetHeight(1000),
                TextInput::make('stock_quantity')
                    ->label('Existencias')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('min_stock')
                    ->label('Stock mínimo')
                    ->numeric()
                    ->default(0),
                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('size')
                    ->label('Talla')
                    ->placeholder('—'),
                TextColumn::make('color')
                    ->label('Color')
                    ->placeholder('—'),
                TextColumn::make('model')
                    ->label('Modelo')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('Precio')
                    ->money('HNL')
                    ->sortable(),
                TextColumn::make('stock_quantity')
                    ->label('Existencias')
                    ->badge()
                    ->color(fn ($record): string => $record->stock_quantity <= $record->min_stock ? 'danger' : 'success')
                    ->alignCenter()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva variante'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
