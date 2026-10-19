<?php

namespace App\Filament\Boutique\Resources\Categories\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubcategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Subcategorías';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Descripción')
                    ->placeholder('Opcional')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Imagen')
                    ->image()
                    ->disk(config('filesystems.media'))
                    ->directory('categories')
                    ->visibility('public')
                    ->maxSize(5120)
                    ->imageResizeMode('contain')
                    ->imageResizeTargetWidth(1200)
                    ->imageResizeTargetHeight(1200)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('image_path')
                    ->label('')
                    ->disk(config('filesystems.media'))
                    ->square()
                    ->defaultImageUrl(asset('images/brand/favicon.svg')),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('products_count')
                    ->label('Productos')
                    ->counts('products')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva subcategoría'),
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
