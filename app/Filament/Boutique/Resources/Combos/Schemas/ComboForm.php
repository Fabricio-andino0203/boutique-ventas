<?php

namespace App\Filament\Boutique\Resources\Combos\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ComboPricing;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class ComboForm
{
    /**
     * Modos de cobro ("Cómo se cobra").
     *
     * @return array<string, string>
     */
    public static function pricingModeOptions(): array
    {
        return [
            'cheapest' => 'Incluye la más barata',
            'up_to_price' => 'Incluye hasta un precio que tú defines',
            'all_same' => 'Todas al mismo precio',
        ];
    }

    /**
     * Formulario de EDICIÓN (por secciones, como el resto del catálogo).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Productos del combo')
                ->description('Qué productos agrupa el combo y cuántas unidades de cada uno.')
                ->icon(Heroicon::OutlinedSquares2x2)
                ->schema([self::itemsRepeater()]),
            Section::make('Nombre y precio')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->schema(self::nameAndPriceFields())
                ->columns(2),
            Section::make('Stock')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->schema(self::stockFields())
                ->columns(2),
        ]);
    }

    /**
     * PASO 1 — Repeater de componentes del combo.
     */
    public static function itemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->hiddenLabel()
            ->addActionLabel('Agregar producto')
            ->defaultItems(1)
            ->reorderable(false)
            ->live()
            ->itemLabel(fn (array $state): ?string => filled($state['product_id'] ?? null)
                ? (Product::find($state['product_id'])?->name ?? 'Producto')
                : 'Nuevo producto')
            ->schema([
                Select::make('product_id')
                    ->label('Producto')
                    ->options(fn (): array => Product::query()
                        ->where('type', '!=', 'combo')
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('product_variant_id', null);
                        $set('eligible_variant_ids', []);
                    })
                    ->columnSpan(2),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required(),
                Toggle::make('choose_at_sale')
                    ->label('Se elige al vender')
                    ->helperText('El cliente escoge la variante al momento de la venta.')
                    ->default(false)
                    ->live()
                    ->visible(fn (Get $get): bool => self::productHasVariants($get('product_id')))
                    ->columnSpanFull(),
                Select::make('product_variant_id')
                    ->label('Variante')
                    ->options(fn (Get $get): array => self::variantOptions($get('product_id')))
                    ->searchable()
                    ->visible(fn (Get $get): bool => ! $get('choose_at_sale') && self::productHasVariants($get('product_id')))
                    ->required(fn (Get $get): bool => ! $get('choose_at_sale') && self::productHasVariants($get('product_id')))
                    ->columnSpanFull(),
                Select::make('eligible_variant_ids')
                    ->label('Variantes elegibles')
                    ->helperText('Dejá vacío para permitir todas las variantes del producto.')
                    ->options(fn (Get $get): array => self::variantOptions($get('product_id')))
                    ->multiple()
                    ->searchable()
                    ->visible(fn (Get $get): bool => (bool) $get('choose_at_sale'))
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    /**
     * PASO 2 — Nombre, categoría, imagen, modo de cobro y precio.
     *
     * @return array<int, Component>
     */
    public static function nameAndPriceFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre del combo')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            Select::make('category_id')
                ->label('Categoría')
                ->options(fn (): array => Category::query()->whereNull('parent_id')->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->live()
                ->placeholder('Opcional')
                ->afterStateUpdated(fn (Set $set) => $set('subcategory_id', null)),
            Select::make('subcategory_id')
                ->label('Subcategoría')
                ->options(fn (Get $get): array => $get('category_id')
                    ? Category::query()->where('parent_id', $get('category_id'))->orderBy('name')->pluck('name', 'id')->all()
                    : [])
                ->searchable()
                ->placeholder('Opcional'),
            FileUpload::make('image_path')
                ->label('Foto del combo')
                ->image()
                ->disk(config('filesystems.media'))
                ->directory('combos')
                ->visibility('public')
                ->maxSize(5120)
                ->imageResizeMode('contain')
                ->imageResizeTargetWidth(1200)
                ->imageResizeTargetHeight(1200)
                ->imageEditor()
                ->helperText('Opcional. Podés subir una foto o dejar la del producto.')
                ->columnSpanFull(),
            ToggleButtons::make('pricing_mode')
                ->label('Cómo se cobra')
                ->options(self::pricingModeOptions())
                ->icons([
                    'cheapest' => Heroicon::OutlinedArrowTrendingDown,
                    'up_to_price' => Heroicon::OutlinedAdjustmentsHorizontal,
                    'all_same' => Heroicon::OutlinedEquals,
                ])
                ->default('cheapest')
                ->live()
                ->helperText('Define cuánto paga de más el cliente si elige una variante distinta.')
                ->columnSpanFull(),
            TextInput::make('base_price')
                ->label('Precio base (hasta donde no hay recargo)')
                ->numeric()
                ->prefix('L')
                ->visible(fn (Get $get): bool => $get('pricing_mode') === 'up_to_price')
                ->helperText('Si la variante elegida cuesta más que esto, el cliente paga la diferencia.')
                ->columnSpanFull(),
            Placeholder::make('recargos')
                ->label('Variantes elegibles y recargo')
                ->content(fn (Get $get) => self::surchargeSummary($get))
                ->columnSpanFull(),
            TextInput::make('price')
                ->label('Cuánto cuesta')
                ->numeric()
                ->required()
                ->prefix('L')
                ->default(0)
                ->live(onBlur: true)
                ->columnSpanFull(),
            Actions::make([
                self::discountAction('menos10', '-10%', 10),
                self::discountAction('menos15', '-15%', 15),
                self::discountAction('menos20', '-20%', 20),
            ])->columnSpanFull(),
            Placeholder::make('ahorro')
                ->label('Lo que ahorra el cliente')
                ->content(fn (Get $get) => self::savingsSummary($get))
                ->columnSpanFull(),
        ];
    }

    /**
     * PASO 3 — Modo de stock.
     *
     * @return array<int, Component>
     */
    public static function stockFields(): array
    {
        return [
            ToggleButtons::make('stock_mode')
                ->label('Cómo se maneja el stock')
                ->options([
                    'derived' => 'Derivado (recomendado)',
                    'own' => 'Propio',
                ])
                ->icons([
                    'derived' => Heroicon::OutlinedArrowPathRoundedSquare,
                    'own' => Heroicon::OutlinedArchiveBox,
                ])
                ->inline()
                ->default('derived')
                ->live()
                ->columnSpanFull(),
            Placeholder::make('stock_derivado_nota')
                ->label('')
                ->content('No necesitás cargar existencias: el combo se puede vender mientras haya stock de sus productos.')
                ->visible(fn (Get $get): bool => $get('stock_mode') !== 'own')
                ->columnSpanFull(),
            Placeholder::make('stock_propio_aviso')
                ->label('')
                ->content('Ojo: con stock propio, vender el combo NO descuenta las existencias de sus productos.')
                ->visible(fn (Get $get): bool => $get('stock_mode') === 'own')
                ->columnSpanFull(),
            TextInput::make('stock_quantity')
                ->label('Stock actual')
                ->numeric()
                ->default(0)
                ->visible(fn (Get $get): bool => $get('stock_mode') === 'own'),
            TextInput::make('min_stock')
                ->label('Stock mínimo')
                ->numeric()
                ->default(0)
                ->visible(fn (Get $get): bool => $get('stock_mode') === 'own'),
        ];
    }

    /** Botón de descuento que fija el precio desde el "por separado". */
    private static function discountAction(string $name, string $label, float $percent): Action
    {
        return Action::make($name)
            ->label($label)
            ->color('gray')
            ->action(function (Get $get, Set $set) use ($percent): void {
                $separate = self::separateFromState($get);
                $set('price', app(ComboPricing::class)->discountedPrice($separate, $percent));
            });
    }

    /** ¿El producto tiene más de una variante (o alguna con atributos)? */
    private static function productHasVariants(mixed $productId): bool
    {
        if (blank($productId)) {
            return false;
        }

        $product = Product::find($productId);

        return $product !== null && ($product->type === 'variable' || $product->variants()->count() > 1);
    }

    /**
     * Opciones de variante de un producto, etiquetadas con su precio.
     *
     * @return array<int, string>
     */
    private static function variantOptions(mixed $productId): array
    {
        if (blank($productId)) {
            return [];
        }

        return ProductVariant::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->orderBy('price')
            ->get()
            ->mapWithKeys(fn (ProductVariant $v): array => [$v->id => self::variantLabel($v)])
            ->all();
    }

    private static function variantLabel(ProductVariant $variant): string
    {
        $desc = collect([$variant->size, $variant->color, $variant->model])->filter()->implode(' / ');
        $desc = $desc !== '' ? $desc : ($variant->name ?: 'Única');

        return $desc.' — L '.number_format((float) $variant->price, 2);
    }

    /**
     * Suma "por separado" a partir del estado del formulario (sin persistir).
     */
    public static function separateFromState(Get $get): float
    {
        $items = (array) $get('items');
        $total = 0.0;

        foreach ($items as $item) {
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            $price = self::representativePriceFromItem($item);
            $total += $price * $quantity;
        }

        return round($total, 2);
    }

    /** Precio representativo de una fila del repeater (variante fija o más barata elegible). */
    private static function representativePriceFromItem(array $item): float
    {
        $variants = self::eligibleVariantsFromItem($item);

        return $variants->isNotEmpty() ? (float) $variants->min('price') : 0.0;
    }

    /**
     * Variantes elegibles de una fila del repeater.
     *
     * @return Collection<int, ProductVariant>
     */
    private static function eligibleVariantsFromItem(array $item): Collection
    {
        if (blank($item['product_id'] ?? null)) {
            return collect();
        }

        $chooseAtSale = (bool) ($item['choose_at_sale'] ?? false);

        if (! $chooseAtSale && filled($item['product_variant_id'] ?? null)) {
            return ProductVariant::query()->where('id', $item['product_variant_id'])->get();
        }

        return ProductVariant::query()
            ->where('product_id', $item['product_id'])
            ->where('is_active', true)
            ->when(
                $chooseAtSale && filled($item['eligible_variant_ids'] ?? null),
                fn ($q) => $q->whereIn('id', (array) $item['eligible_variant_ids'])
            )
            ->orderBy('price')
            ->get();
    }

    /** Texto de "Lo que ahorra el cliente". */
    private static function savingsSummary(Get $get): string
    {
        $separate = self::separateFromState($get);
        $price = (float) $get('price');
        $amount = max(0.0, $separate - $price);
        $percent = $separate > 0 ? round(($amount / $separate) * 100, 1) : 0.0;

        return 'Por separado: L '.number_format($separate, 2)
            .'  ·  Combo: L '.number_format($price, 2)
            .'  ·  Ahorra: L '.number_format($amount, 2).' ('.number_format($percent, 1).'%)';
    }

    /** Resumen de variantes elegibles con su recargo, por fila "a elegir". */
    private static function surchargeSummary(Get $get): string
    {
        $items = (array) $get('items');
        $mode = (string) ($get('pricing_mode') ?? 'cheapest');
        $basePrice = (float) $get('base_price');
        $lines = [];

        foreach ($items as $item) {
            if (! ($item['choose_at_sale'] ?? false)) {
                continue;
            }

            $variants = self::eligibleVariantsFromItem($item);
            if ($variants->isEmpty()) {
                continue;
            }

            $cheapest = (float) $variants->min('price');
            $productName = Product::find($item['product_id'] ?? null)?->name ?? 'Producto';

            foreach ($variants as $variant) {
                $surcharge = match ($mode) {
                    'up_to_price' => max(0.0, (float) $variant->price - $basePrice),
                    'all_same' => 0.0,
                    default => max(0.0, (float) $variant->price - $cheapest),
                };

                $tag = $surcharge > 0 ? '+ L '.number_format($surcharge, 2) : 'Sin recargo';
                $lines[] = $productName.' · '.self::variantLabel($variant).'  →  '.$tag;
            }
        }

        return $lines === [] ? 'Agregá productos "a elegir" para ver sus variantes y recargos.' : implode("\n", $lines);
    }
}
