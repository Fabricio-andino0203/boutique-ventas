<?php

namespace App\Filament\Boutique\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Support\Barcode;
use App\Support\CurrentTenant;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class ProductForm
{
    /**
     * Opciones del selector de tipo de producto (3 tipos, estilo Scrampi).
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        return [
            'simple' => 'Simple',
            'variable' => 'Variable',
            'custom' => 'Precio personalizado',
        ];
    }

    /**
     * Formulario de EDICIÓN (por secciones). Las variantes se administran en el
     * gestor de variantes de la propia pantalla de edición.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información básica')
                    ->schema([
                        ToggleButtons::make('type')
                            ->label('Tipo de producto')
                            ->options(self::typeOptions())
                            ->icons([
                                'simple' => Heroicon::OutlinedCube,
                                'variable' => Heroicon::OutlinedSquares2x2,
                                'custom' => Heroicon::OutlinedCurrencyDollar,
                            ])
                            ->inline()
                            ->default('simple')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('El tipo no se cambia después de crear el producto.')
                            ->columnSpanFull(),
                        ...self::basicFields(),
                    ])
                    ->columns(2),
                Section::make('Precios e impuestos (referencia)')
                    ->description('En productos variables, el precio real vive en cada variante.')
                    ->schema([
                        ...self::pricingFields(),
                        ...self::optionsAndTaxFields(),
                    ])
                    ->columns(2),
                Section::make('Imágenes')
                    ->schema(self::imageFields()),
            ]);
    }

    /**
     * Campos de datos básicos del producto (sin el selector de tipo).
     *
     * @return array<int, Component>
     */
    public static function basicFields(): array
    {
        return [
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
            Select::make('category_id')
                ->label('Categoría')
                ->options(fn (): array => Category::query()->whereNull('parent_id')->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->live()
                ->placeholder('Opcional')
                ->afterStateUpdated(fn (Set $set) => $set('subcategory_id', null))
                ->createOptionForm([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                ])
                ->createOptionUsing(fn (array $data): int => Category::create(['name' => $data['name']])->getKey()),
            Select::make('subcategory_id')
                ->label('Subcategoría')
                ->options(fn (Get $get): array => $get('category_id')
                    ? Category::query()->where('parent_id', $get('category_id'))->orderBy('name')->pluck('name', 'id')->all()
                    : [])
                ->searchable()
                ->placeholder('Opcional')
                ->createOptionForm([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                ])
                ->createOptionUsing(fn (array $data, Get $get): ?int => $get('category_id')
                    ? Category::create(['name' => $data['name'], 'parent_id' => $get('category_id')])->getKey()
                    : null),
            Select::make('brand_id')
                ->label('Marca')
                ->relationship('brand', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Opcional')
                ->createOptionForm([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                ]),
            self::barcodeField(),
            Select::make('supplier_id')
                ->label('Proveedor')
                ->relationship('supplier', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Opcional')
                ->createOptionForm([
                    TextInput::make('name')->label('Nombre')->required()->maxLength(255),
                ]),
            Select::make('unit')
                ->label('Unidad de medida')
                ->options([
                    'Unidad' => 'Unidad',
                    'Par' => 'Par',
                    'Juego completo' => 'Juego completo',
                    'Set' => 'Set',
                    'Docena' => 'Docena',
                    'Paquete' => 'Paquete',
                    'Caja' => 'Caja',
                    'Metro' => 'Metro',
                    'Kilogramo' => 'Kilogramo',
                ])
                ->searchable()
                ->default('Unidad')
                ->placeholder('Seleccioná una unidad'),
        ];
    }

    /**
     * Código de barras con botón para generar un EAN-13 válido.
     */
    public static function barcodeField(): TextInput
    {
        return TextInput::make('barcode')
            ->label('Código de barras')
            ->maxLength(255)
            ->suffixAction(
                Action::make('generarCodigo')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->tooltip('Generar código')
                    ->action(fn (Set $set) => $set('barcode', Barcode::generateEan13()))
            );
    }

    /**
     * Precio / costo / stock a nivel de producto (valores por defecto).
     * Se ocultan en productos de tipo "Precio personalizado".
     *
     * @return array<int, Component>
     */
    public static function pricingFields(): array
    {
        return [
            TextInput::make('default_price')
                ->label('Precio de venta')
                ->numeric()
                ->default(0)
                ->prefix('L')
                ->live(onBlur: true)
                ->visible(fn (Get $get): bool => $get('type') !== 'custom'),
            TextInput::make('default_cost')
                ->label('Costo')
                ->numeric()
                ->default(0)
                ->prefix('L')
                ->live(onBlur: true)
                ->visible(fn (Get $get): bool => $get('type') !== 'custom'),
            self::utilityPlaceholder('default_price', 'default_cost')
                ->visible(fn (Get $get): bool => $get('type') !== 'custom'),
            TextInput::make('wholesale_price')
                ->label('Precio mayorista')
                ->numeric()
                ->prefix('L')
                ->placeholder('Opcional')
                ->visible(fn (Get $get): bool => $get('type') !== 'custom'),
            TextInput::make('min_stock')
                ->label('Stock mínimo')
                ->numeric()
                ->default(0),
        ];
    }

    /**
     * Opciones de inventario e impuestos (estilo Scrampi).
     *
     * @return array<int, Component>
     */
    public static function optionsAndTaxFields(): array
    {
        return [
            Toggle::make('manage_stock')
                ->label('Gestionar stock')
                ->helperText('El stock del producto se descontará al crear una venta.')
                ->default(true),
            Toggle::make('variable_price')
                ->label('Precio variable')
                ->helperText('Permite modificar el precio al momento de la venta y agregar descuentos.')
                ->default(false),
            Toggle::make('allow_negative_stock')
                ->label('Stock negativo')
                ->helperText('Permite vender el producto aunque ya no se encuentren existencias.')
                ->default(false),
            Toggle::make('allow_discount')
                ->label('Permitir descuentos')
                ->default(true),
            ToggleButtons::make('tax_type')
                ->label('Impuesto (ISV)')
                ->options(['none' => 'Sin impuesto', 'included' => 'Incluido en el precio', 'additional' => 'Adicional'])
                ->icons([
                    'none' => Heroicon::OutlinedNoSymbol,
                    'included' => Heroicon::OutlinedCheckCircle,
                    'additional' => Heroicon::OutlinedPlusCircle,
                ])
                ->inline()
                ->default('none')
                ->live()
                ->helperText('Incluido: el precio ya contiene el impuesto. Adicional: se suma al precio (ej. L 100 + 15% = L 115).')
                ->columnSpanFull(),
            TextInput::make('tax_rate')
                ->label('Tasa de impuesto (%)')
                ->numeric()
                ->default(15)
                ->suffix('%')
                ->visible(fn (Get $get): bool => $get('tax_type') !== 'none'),
        ];
    }

    /**
     * Muestra la utilidad (precio - costo) y el margen en %, de forma reactiva.
     */
    public static function utilityPlaceholder(string $priceKey, string $costKey): Placeholder
    {
        return Placeholder::make('utility_display')
            ->label('Utilidad')
            ->content(function (Get $get) use ($priceKey, $costKey): string {
                $price = (float) $get($priceKey);
                $cost = (float) $get($costKey);
                $profit = $price - $cost;
                $margin = $price > 0 ? ($profit / $price) * 100 : 0;

                return 'L '.number_format($profit, 2).'  ('.number_format($margin, 1).'% de margen)';
            });
    }

    /**
     * Imagen principal + galería + propagación a variantes sin imagen.
     *
     * @return array<int, Component>
     */
    public static function imageFields(): array
    {
        return [
            FileUpload::make('image_path')
                ->label('Imagen principal')
                ->image()
                ->disk(config('filesystems.media'))
                ->directory('products')
                ->visibility('public')
                ->maxSize(5120)
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth(1200)
                ->imageResizeTargetHeight(1200)
                ->imageEditor()
                ->helperText('Portada del producto. Opcional. Máximo 5 MB; se optimiza automáticamente.')
                ->columnSpanFull(),
            Toggle::make('propagate_main_image')
                ->label('Usar la imagen principal en las variantes sin imagen')
                ->default(false)
                ->columnSpanFull(),
            FileUpload::make('gallery')
                ->label('Galería (hasta 10 imágenes)')
                ->image()
                ->multiple()
                ->reorderable()
                ->appendFiles()
                ->maxFiles(10)
                ->disk(config('filesystems.media'))
                ->directory('products/gallery')
                ->visibility('public')
                ->maxSize(5120)
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth(1200)
                ->imageResizeTargetHeight(1200)
                ->helperText('Imágenes adicionales para el catálogo.')
                ->columnSpanFull(),
        ];
    }

    /**
     * Fila de variante para el repeater (producto variable).
     *
     * @return array<int, Component>
     */
    public static function variantFields(): array
    {
        return [
            TextInput::make('color')->label('Color'),
            TextInput::make('size')->label('Talla'),
            TextInput::make('model')->label('Modelo'),
            TextInput::make('price')->label('Precio')->numeric()->default(0)->prefix('L')->live(onBlur: true),
            TextInput::make('cost')->label('Costo')->numeric()->default(0)->prefix('L')->live(onBlur: true),
            self::utilityPlaceholder('price', 'cost'),
            TextInput::make('wholesale_price')->label('Mayorista')->numeric()->prefix('L'),
            TextInput::make('stock_quantity')->label('Existencias')->numeric()->default(0),
            TextInput::make('min_stock')->label('Stock mínimo')->numeric()->default(0),
            TextInput::make('sku')->label('Código (SKU)'),
            TextInput::make('barcode')
                ->label('Código de barras')
                ->suffixAction(
                    Action::make('generarCodigoVariante')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->tooltip('Generar código')
                        ->action(fn (Set $set) => $set('barcode', Barcode::generateEan13()))
                ),
            FileUpload::make('image_path')
                ->label('Imagen de la variante')
                ->image()
                ->disk(config('filesystems.media'))
                ->directory('variants')
                ->visibility('public')
                ->maxSize(5120)
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth(1000)
                ->imageResizeTargetHeight(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * Fila de variante GENERADA (para el repeater del generador de combinaciones).
     * Incluye el nombre compuesto y las opciones (ocultas) para guardarlas.
     *
     * @return array<int, Component>
     */
    public static function generatedVariantFields(): array
    {
        return [
            TextInput::make('name')->label('Nombre')->columnSpan(2),
            Hidden::make('options'),
            Hidden::make('size'),
            Hidden::make('color'),
            Hidden::make('model'),
            TextInput::make('price')->label('Precio')->numeric()->default(0)->prefix('L')->live(onBlur: true),
            TextInput::make('cost')->label('Costo')->numeric()->default(0)->prefix('L')->live(onBlur: true),
            self::utilityPlaceholder('price', 'cost'),
            TextInput::make('wholesale_price')->label('Mayorista')->numeric()->prefix('L'),
            TextInput::make('stock_quantity')->label('Existencias')->numeric()->default(0),
            TextInput::make('min_stock')->label('Stock mínimo')->numeric()->default(0),
            TextInput::make('barcode')
                ->label('Código de barras')
                ->suffixAction(
                    Action::make('generarCodigoGenerada')
                        ->icon(Heroicon::OutlinedSparkles)
                        ->tooltip('Generar código')
                        ->action(fn (Set $set) => $set('barcode', Barcode::generateEan13()))
                ),
            FileUpload::make('image_path')
                ->label('Imagen de la variante')
                ->image()
                ->disk(config('filesystems.media'))
                ->directory('variants')
                ->visibility('public')
                ->maxSize(5120)
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth(1000)
                ->imageResizeTargetHeight(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * Producto cartesiano de las opciones de variante.
     * Entrada: [['name'=>'Talla','values'=>['S','M']], ['name'=>'Color','values'=>['Rojo']]]
     * Salida:  [['Talla'=>'S','Color'=>'Rojo'], ['Talla'=>'M','Color'=>'Rojo']]
     *
     * @param  array<int, array<string, mixed>>  $options
     * @return array<int, array<string, string>>
     */
    public static function cartesian(array $options): array
    {
        $result = [[]];

        foreach ($options as $option) {
            $name = trim((string) ($option['name'] ?? ''));
            $values = array_values(array_filter((array) ($option['values'] ?? []), fn ($v): bool => filled($v)));

            if ($name === '' || $values === []) {
                continue;
            }

            $next = [];
            foreach ($result as $combo) {
                foreach ($values as $value) {
                    $next[] = $combo + [$name => (string) $value];
                }
            }
            $result = $next;
        }

        return $result === [[]] ? [] : $result;
    }

    /**
     * Construye las variantes (para el repeater) a partir de las opciones,
     * los valores por defecto y el separador de nombre.
     *
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<string, mixed>  $defaults
     * @return array<int, array<string, mixed>>
     */
    public static function buildVariantsFromOptions(array $options, array $defaults, string $separator, string $productName): array
    {
        $separator = $separator !== '' ? $separator : ' - ';
        $price = (float) ($defaults['price'] ?? 0);
        $cost = (float) ($defaults['cost'] ?? 0);
        $stock = (int) ($defaults['stock'] ?? 0);

        $variants = [];
        foreach (self::cartesian($options) as $combo) {
            $valuesText = implode($separator, array_values($combo));

            $variants[] = [
                'name' => trim($productName.$separator.$valuesText),
                'options' => $combo,
                'size' => self::pickOption($combo, ['talla', 'size', 'tamaño', 'tamano']),
                'color' => self::pickOption($combo, ['color']),
                'model' => self::pickOption($combo, ['modelo', 'model', 'material', 'estilo']),
                'price' => $price,
                'cost' => $cost,
                'wholesale_price' => null,
                'stock_quantity' => $stock,
                'min_stock' => 0,
                'sku' => null,
                'barcode' => null,
                'image_path' => null,
                'is_active' => true,
            ];
        }

        return $variants;
    }

    /**
     * Busca en la combinación un valor cuyo nombre de opción coincida con alguno
     * de los nombres dados (para mapear a las columnas size/color/model).
     *
     * @param  array<string, string>  $combo
     * @param  array<int, string>  $names
     */
    private static function pickOption(array $combo, array $names): ?string
    {
        foreach ($combo as $optionName => $value) {
            if (in_array(mb_strtolower(trim((string) $optionName)), $names, true)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Crea la variante única de un producto simple / de precio personalizado.
     *
     * @param  array<string, mixed>  $data
     */
    public static function syncSimpleVariant(Product $product, array $data): void
    {
        $product->variants()->create([
            'tenant_id' => $product->tenant_id ?? app(CurrentTenant::class)->id() ?? Auth::user()?->tenant_id,
            'name' => $product->name,
            'sku' => $data['code'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'price' => $data['default_price'] ?? 0,
            'cost' => $data['default_cost'] ?? 0,
            'wholesale_price' => $data['wholesale_price'] ?? null,
            'stock_quantity' => (int) ($data['simple_stock'] ?? 0),
            'min_stock' => (int) ($data['min_stock'] ?? 0),
            'is_active' => true,
        ]);
    }
}
