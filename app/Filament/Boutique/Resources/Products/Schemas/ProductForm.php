<?php

namespace App\Filament\Boutique\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Support\CurrentTenant;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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
                            ->options(['simple' => 'Simple', 'variable' => 'Variable'])
                            ->icons(['simple' => Heroicon::OutlinedCube, 'variable' => Heroicon::OutlinedSquares2x2])
                            ->inline()
                            ->default('simple')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('El tipo no se cambia después de crear el producto.'),
                        ...self::basicFields(),
                    ])
                    ->columns(2),
                Section::make('Precios e impuestos (referencia)')
                    ->description('En productos variables, el precio real vive en cada variante.')
                    ->schema(self::pricingFields())
                    ->columns(2),
                Section::make('Imagen')
                    ->schema([self::imageField()]),
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
            Select::make('category_id')
                ->label('Categoría')
                ->options(fn (): array => Category::query()->whereNull('parent_id')->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('subcategory_id', null)),
            Select::make('subcategory_id')
                ->label('Subcategoría')
                ->options(fn (Get $get): array => $get('category_id')
                    ? Category::query()->where('parent_id', $get('category_id'))->orderBy('name')->pluck('name', 'id')->all()
                    : [])
                ->searchable()
                ->placeholder('Opcional'),
            TextInput::make('brand')
                ->label('Marca')
                ->maxLength(255),
            Select::make('supplier_id')
                ->label('Proveedor')
                ->relationship('supplier', 'name')
                ->searchable()
                ->preload()
                ->placeholder('Opcional'),
            TextInput::make('code')
                ->label('Código')
                ->maxLength(255)
                ->placeholder('Interno (opcional)'),
            TextInput::make('unit')
                ->label('Unidad')
                ->placeholder('Ej. Unidad, Par, Juego')
                ->maxLength(50),
            Textarea::make('description')
                ->label('Descripción')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }

    /**
     * Código de barras con botón para generarlo. Solo aplica a producto simple.
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
                    ->action(fn (Set $set) => $set('barcode', (string) random_int(1_000_000_000_000, 9_999_999_999_999)))
            );
    }

    /**
     * Campos de precio / impuestos a nivel de producto.
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
                ->live(onBlur: true),
            TextInput::make('default_cost')
                ->label('Costo')
                ->numeric()
                ->default(0)
                ->prefix('L')
                ->live(onBlur: true),
            self::utilityPlaceholder('default_price', 'default_cost'),
            TextInput::make('wholesale_price')
                ->label('Precio mayorista')
                ->numeric()
                ->prefix('L')
                ->placeholder('Opcional'),
            TextInput::make('min_stock')
                ->label('Stock mínimo')
                ->numeric()
                ->default(0),
            Toggle::make('allow_discount')
                ->label('Permitir descuentos')
                ->default(true),
            ToggleButtons::make('tax_type')
                ->label('Impuesto (ISV)')
                ->options(['none' => 'Sin impuesto', 'included' => 'Incluido en el precio', 'additional' => 'Adicional'])
                ->inline()
                ->default('none')
                ->live(),
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

    public static function imageField(): FileUpload
    {
        return FileUpload::make('image_path')
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
            ->helperText('Opcional. Máximo 5 MB; se optimiza automáticamente.')
            ->columnSpanFull();
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
            TextInput::make('barcode')->label('Código de barras'),
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
     * Crea la variante única de un producto simple a partir de los datos del form.
     *
     * @param  array<string, mixed>  $data
     */
    public static function syncSimpleVariant(Product $product, array $data): void
    {
        $product->variants()->create([
            'tenant_id' => $product->tenant_id ?? app(CurrentTenant::class)->id() ?? Auth::user()?->tenant_id,
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
