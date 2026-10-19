<?php

namespace App\Filament\Boutique\Pages;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\VariantBarcode;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Computed;

class Barcodes extends Page
{
    protected string $view = 'filament.boutique.pages.barcodes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'Carga masiva';

    protected static ?int $navigationSort = 3;

    public const MAX_LABELS = 300;

    public string $search = '';

    public ?int $expandedProduct = null;

    /** @var array<int, array{variant_id:int, name:string, code:string, price:float, stock:int, quantity:int}> */
    public array $selected = [];

    // Opciones de impresión.
    public bool $showName = true;

    public bool $showPrice = true;

    public string $belowBars = 'code';

    public string $printer = 'normal';

    public int $labelWidth = 50;

    public int $labelHeight = 25;

    public int $perRow = 1;

    public static function getNavigationLabel(): string
    {
        return 'Códigos de barras';
    }

    public function getTitle(): string
    {
        return 'Códigos de barras';
    }

    public function getSubheading(): ?string
    {
        return 'Elegí tus productos e imprimí sus etiquetas con código de barras, nombre y precio, en hoja normal o impresora térmica.';
    }

    /**
     * @return Collection<int, Product>
     */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->with('variants')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($q): void {
                    $q->where('name', 'like', '%'.$this->search.'%')
                        ->orWhereHas('variants', fn ($v) => $v->where('barcode', 'like', '%'.$this->search.'%'));
                });
            })
            ->orderBy('name')
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function totalLabels(): int
    {
        return array_sum(array_column($this->selected, 'quantity'));
    }

    public function updatedSearch(string $value): void
    {
        $value = trim($value);

        // Lector de código de barras: al escanear un EAN-13 válido, agrega la variante sola.
        if (preg_match('/^\d{13}$/', $value) === 1) {
            $variant = ProductVariant::query()->where('barcode', $value)->first();
            if ($variant) {
                $this->addVariant($variant->id);
                $this->search = '';
            }
        }
    }

    public function toggleExpand(int $productId): void
    {
        $this->expandedProduct = $this->expandedProduct === $productId ? null : $productId;
    }

    public function addProduct(int $productId): void
    {
        $product = Product::with('variants')->find($productId);
        if (! $product) {
            return;
        }

        if ($product->variants->count() === 1) {
            $this->addVariant($product->variants->first()->id);

            return;
        }

        $this->toggleExpand($productId);
    }

    public function addVariant(int $variantId): void
    {
        if (isset($this->selected[$variantId])) {
            $this->increment($variantId);

            return;
        }

        if ($this->totalLabels >= self::MAX_LABELS) {
            $this->warnMax();

            return;
        }

        $variant = ProductVariant::with('product')->find($variantId);
        if (! $variant) {
            return;
        }

        $code = app(VariantBarcode::class)->ensure($variant);

        $this->selected[$variantId] = [
            'variant_id' => $variantId,
            'name' => $this->labelName($variant),
            'code' => $code,
            'price' => (float) $variant->price,
            'stock' => (int) $variant->stock_quantity,
            'quantity' => 1,
        ];

        unset($this->totalLabels);
    }

    public function addPage(): void
    {
        foreach ($this->products as $product) {
            foreach ($product->variants as $variant) {
                if ($this->totalLabels >= self::MAX_LABELS) {
                    $this->warnMax();

                    return;
                }
                $this->addVariant($variant->id);
            }
        }
    }

    public function increment(int $variantId): void
    {
        if (! isset($this->selected[$variantId])) {
            return;
        }
        if ($this->totalLabels >= self::MAX_LABELS) {
            $this->warnMax();

            return;
        }
        $this->selected[$variantId]['quantity']++;
        unset($this->totalLabels);
    }

    public function decrement(int $variantId): void
    {
        if (! isset($this->selected[$variantId])) {
            return;
        }
        $this->selected[$variantId]['quantity']--;
        if ($this->selected[$variantId]['quantity'] <= 0) {
            $this->remove($variantId);
        }
        unset($this->totalLabels);
    }

    public function remove(int $variantId): void
    {
        unset($this->selected[$variantId]);
        unset($this->totalLabels);
    }

    public function limpiar(): void
    {
        $this->selected = [];
        unset($this->totalLabels);
    }

    /** Una etiqueta por cada unidad en stock de cada ítem seleccionado. */
    public function segunStock(): void
    {
        foreach ($this->selected as $variantId => $item) {
            $this->selected[$variantId]['quantity'] = max(1, (int) $item['stock']);
        }

        // Recorta al máximo permitido conservando el orden.
        $running = 0;
        foreach ($this->selected as $variantId => $item) {
            if ($running >= self::MAX_LABELS) {
                unset($this->selected[$variantId]);

                continue;
            }
            $allowed = min($item['quantity'], self::MAX_LABELS - $running);
            $this->selected[$variantId]['quantity'] = $allowed;
            $running += $allowed;
        }

        unset($this->totalLabels);
    }

    public function imprimir(): ?RedirectResponse
    {
        if ($this->selected === []) {
            Notification::make()->title('Agregá al menos un producto.')->danger()->send();

            return null;
        }

        session()->put('barcode_labels', [
            'items' => array_values($this->selected),
            'options' => [
                'showName' => $this->showName,
                'showPrice' => $this->showPrice,
                'belowBars' => $this->belowBars,
                'printer' => $this->printer,
                'labelWidth' => $this->labelWidth,
                'labelHeight' => $this->labelHeight,
                'perRow' => max(1, $this->perRow),
            ],
        ]);

        return redirect()->route('boutique.barcodes.print');
    }

    private function labelName(ProductVariant $variant): string
    {
        $base = $variant->product?->name ?? 'Producto';
        $suffix = trim((string) ($variant->size ?? '').' '.(string) ($variant->color ?? ''));
        if ($suffix === '' && filled($variant->name) && $variant->name !== $base) {
            $suffix = str_replace($base.' - ', '', $variant->name);
        }

        return trim($base.($suffix !== '' ? ' · '.$suffix : ''));
    }

    private function warnMax(): void
    {
        Notification::make()
            ->title('Máximo '.self::MAX_LABELS.' etiquetas por impresión.')
            ->warning()
            ->send();
    }
}
