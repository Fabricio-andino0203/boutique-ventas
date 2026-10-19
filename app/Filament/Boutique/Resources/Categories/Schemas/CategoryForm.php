<?php

namespace App\Filament\Boutique\Resources\Categories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos de la categoría')
                    ->description('Una categoría agrupa tus productos. Luego podrás agregarle subcategorías.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Opcional')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        FileUpload::make('image_path')
                            ->label('Imagen de la categoría')
                            ->image()
                            ->disk(config('filesystems.media'))
                            ->directory('categories')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->imageResizeMode('contain')
                            ->imageResizeTargetWidth(1200)
                            ->imageResizeTargetHeight(1200)
                            ->imageEditor()
                            ->helperText('Opcional. Máximo 5 MB; se optimiza automáticamente.')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }
}
