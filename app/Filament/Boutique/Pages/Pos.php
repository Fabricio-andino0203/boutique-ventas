<?php

namespace App\Filament\Boutique\Pages;

use App\Actions\RegisterSale;
use App\Models\CashRegister;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Services\ComboPricing;
use App\Services\ComboService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

class Pos extends Page
{
    protected string $view = 'filament.boutique.pages.pos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|\UnitEnum|null $navigationGroup = 'Operación diaria';

    protected static ?string $navigationLabel = 'Punto de venta';

    protected static ?int $navigationSort = -10;

    /**
     * Carrito. La clave es un string: 'v{id}' para variantes normales y
     * 'c{comboId}-{variantesElegidas}' para combos.
     *
     * @var array<string, array{key:string, label:string, price:float, qty:int, stock:int, variant_id:int, is_combo:bool, combo_id:int|null, components:array<int, array{variant_id:int, quantity:int}>|null}>
     */
    public array $cart = [];

    public string $search = '';

    public ?int $customerId = null;

    public string $paymentMethod = 'cash';

    public float $amountPaid = 0;

    public float $discount = 0;

    /** Combo que se está configurando (elección de variantes "a elegir"). */
    public ?int $comboSelecting = null;

    /**
     * Variante elegida por cada componente del combo en configuración.
     *
     * @var array<int, int|null>
     */
    public array $comboSelections = [];

    public static function getNavigationLabel(): string
    {
        return 'Punto de venta';
    }

    public function getTitle(): string
    {
        return 'Punto de venta';
    }

    /**
     * Variantes que coinciden con la búsqueda (aisladas por comercio vía TenantScope).
     *
     * @return Collection<int, ProductVariant>
     */
    #[Computed]
    public function results(): Collection
    {
        if (mb_strlen(trim($this->search)) < 1) {
            return collect();
        }

        // Búsqueda sin distinguir mayúsculas, compatible con SQLite (dev) y
        // PostgreSQL (prod): lower(col) like lower(término).
        $term = '%'.mb_strtolower(trim($this->search)).'%';

        return ProductVariant::query()
            ->where('is_active', true)
            ->with('product')
            ->where(function ($query) use ($term): void {
                $query->whereRaw('lower(sku) like ?', [$term])
                    ->orWhereRaw('lower(barcode) like ?', [$term])
                    ->orWhereRaw('lower(color) like ?', [$term])
                    ->orWhereRaw('lower(size) like ?', [$term])
                    ->orWhereHas('product', fn ($q) => $q->whereRaw('lower(name) like ?', [$term]));
            })
            ->limit(12)
            ->get();
    }

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customers(): Collection
    {
        return Customer::query()->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * Caja abierta del vendedor actual, a la que se ligarán las ventas.
     */
    #[Computed]
    public function openCashRegister(): ?CashRegister
    {
        return CashRegister::query()
            ->where('opened_by', Auth::id())
            ->where('status', 'open')
            ->latest('opened_at')
            ->first();
    }

    public function addToCart(int $variantId): void
    {
        $variant = ProductVariant::query()->with('product')->find($variantId);

        if ($variant === null) {
            return;
        }

        if (($variant->product->type ?? null) === 'combo') {
            $this->startCombo($variant);

            return;
        }

        $key = 'v'.$variant->id;

        if (isset($this->cart[$key])) {
            $this->incrementQty($key);

            return;
        }

        if ($variant->stock_quantity < 1) {
            Notification::make()->title('Sin existencias')->danger()->send();

            return;
        }

        $label = trim(($variant->product->name ?? 'Producto').' '.collect([$variant->size, $variant->color, $variant->model])->filter()->implode(' / '));

        $this->cart[$key] = [
            'key' => $key,
            'label' => $label,
            'price' => (float) $variant->price,
            'qty' => 1,
            'stock' => (int) $variant->stock_quantity,
            'variant_id' => $variant->id,
            'is_combo' => false,
            'combo_id' => null,
            'components' => null,
        ];

        $this->search = '';
    }

    /**
     * Inicia el flujo de un combo: si tiene componentes "a elegir" abre el
     * selector; si no, lo agrega directo.
     */
    private function startCombo(ProductVariant $comboVariant): void
    {
        $combo = Combo::query()
            ->with('items.product')
            ->where('product_variant_id', $comboVariant->id)
            ->first();

        if ($combo === null) {
            return;
        }

        $hasChoices = $combo->items->contains(fn (ComboItem $item): bool => $item->isChooseAtSale());

        if (! $hasChoices) {
            $this->putComboInCart($combo, []);
            $this->search = '';

            return;
        }

        // Prepara el selector con la variante más barata por defecto.
        $this->comboSelections = [];
        foreach ($combo->items as $item) {
            if ($item->isChooseAtSale()) {
                $this->comboSelections[$item->id] = $item->eligibleVariants()->first()?->id;
            }
        }

        $this->comboSelecting = $combo->id;
    }

    /** Confirma la elección de variantes del combo y lo agrega al carrito. */
    public function confirmCombo(): void
    {
        $combo = Combo::query()->with('items.product')->find($this->comboSelecting);

        if ($combo === null) {
            $this->cancelCombo();

            return;
        }

        $selections = [];
        foreach ($combo->items as $item) {
            if ($item->isChooseAtSale()) {
                $variantId = $this->comboSelections[$item->id] ?? null;
                if ($variantId === null) {
                    Notification::make()->title('Elegí una variante para cada producto')->warning()->send();

                    return;
                }
                $selections[$item->id] = (int) $variantId;
            }
        }

        $this->putComboInCart($combo, $selections);
        $this->cancelCombo();
        $this->search = '';
    }

    public function cancelCombo(): void
    {
        $this->comboSelecting = null;
        $this->comboSelections = [];
    }

    /**
     * Arma la línea del combo (precio con recargos, componentes y stock) y la
     * agrega al carrito.
     *
     * @param  array<int, int>  $selections  combo_item_id => variant_id elegido
     */
    private function putComboInCart(Combo $combo, array $selections): void
    {
        $combo->loadMissing('items.product', 'variant');

        $chosenByItem = [];
        $components = [];
        $labelParts = [];

        foreach ($combo->items as $item) {
            $variantId = $item->isChooseAtSale()
                ? ($selections[$item->id] ?? null)
                : $item->product_variant_id;

            $variant = $variantId ? ProductVariant::find($variantId) : null;

            if ($variant !== null) {
                $chosenByItem[$item->id] = $variant;
                $components[] = ['variant_id' => $variant->id, 'quantity' => max(1, (int) $item->quantity)];
                $desc = collect([$variant->size, $variant->color, $variant->model])->filter()->implode(' ');
                $labelParts[] = ($item->product?->name ?? 'Producto').($desc !== '' ? " ($desc)" : '');
            }
        }

        $price = app(ComboPricing::class)->priceForSelections($combo, $chosenByItem);

        $available = $combo->stock_mode === 'own'
            ? (int) $combo->stock_quantity
            : app(ComboService::class)->derivedAvailability($combo);

        if ($available < 1) {
            Notification::make()->title('Sin existencias para el combo')->danger()->send();

            return;
        }

        $key = 'c'.$combo->id.'-'.implode('_', $selections);

        if (isset($this->cart[$key])) {
            $this->incrementQty($key);

            return;
        }

        $this->cart[$key] = [
            'key' => $key,
            'label' => $combo->name.' · '.implode(' + ', $labelParts),
            'price' => $price,
            'qty' => 1,
            'stock' => $available,
            'variant_id' => $combo->product_variant_id,
            'is_combo' => true,
            'combo_id' => $combo->id,
            // Stock propio: sin componentes (se descuenta la variante combo).
            'components' => $combo->stock_mode === 'own' ? null : $components,
        ];
    }

    /**
     * Variantes elegibles por componente del combo en configuración, con su recargo.
     *
     * @return array<int, array{item:ComboItem, options:array<int, string>}>
     */
    #[Computed]
    public function comboChoices(): array
    {
        $combo = $this->comboSelecting ? Combo::query()->with('items.product')->find($this->comboSelecting) : null;

        if ($combo === null) {
            return [];
        }

        $pricing = app(ComboPricing::class);
        $choices = [];

        foreach ($combo->items as $item) {
            if (! $item->isChooseAtSale()) {
                continue;
            }

            $options = [];
            foreach ($item->eligibleVariants() as $variant) {
                $surcharge = $pricing->surcharge($combo, $item, $variant);
                $desc = collect([$variant->size, $variant->color, $variant->model])->filter()->implode(' / ');
                $desc = $desc !== '' ? $desc : ($variant->name ?: 'Única');
                $tag = $surcharge > 0 ? ' (+ L '.number_format($surcharge, 2).')' : ' (sin recargo)';
                $options[$variant->id] = $desc.$tag;
            }

            $choices[] = ['item' => $item, 'options' => $options];
        }

        return $choices;
    }

    public function comboName(): ?string
    {
        return $this->comboSelecting ? Combo::find($this->comboSelecting)?->name : null;
    }

    public function incrementQty(string $key): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        if ($this->cart[$key]['qty'] < $this->cart[$key]['stock']) {
            $this->cart[$key]['qty']++;
        } else {
            Notification::make()->title('Alcanzaste el stock disponible')->warning()->send();
        }
    }

    public function decrementQty(string $key): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        $this->cart[$key]['qty']--;

        if ($this->cart[$key]['qty'] < 1) {
            unset($this->cart[$key]);
        }
    }

    public function removeFromCart(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function subtotal(): float
    {
        return collect($this->cart)->sum(fn (array $item): float => $item['price'] * $item['qty']);
    }

    public function total(): float
    {
        return max(0.0, $this->subtotal() - max(0.0, (float) $this->discount));
    }

    public function change(): float
    {
        return max(0.0, (float) $this->amountPaid - $this->total());
    }

    public function register(RegisterSale $action): void
    {
        $items = collect($this->cart)
            ->map(function (array $item): array {
                $line = [
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['qty'],
                    'unit_price' => $item['price'],
                ];

                if ($item['is_combo']) {
                    $line['combo_id'] = $item['combo_id'];
                    if ($item['components'] !== null) {
                        $line['components'] = $item['components'];
                    }
                }

                return $line;
            })
            ->values()
            ->all();

        try {
            $sale = $action->execute($items, [
                'customer_id' => $this->customerId,
                'payment_method' => $this->paymentMethod,
                'amount_paid' => (float) $this->amountPaid,
                'discount' => (float) $this->discount,
                'cash_register_id' => $this->openCashRegister?->id,
            ]);
        } catch (ValidationException $e) {
            Notification::make()
                ->title('No se pudo registrar la venta')
                ->body(collect($e->errors())->flatten()->first())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title("Venta {$sale->number} registrada")
            ->body('Total: L '.number_format($sale->total, 2))
            ->success()
            ->actions([
                Action::make('recibo')
                    ->label('Imprimir recibo')
                    ->icon(Heroicon::OutlinedPrinter)
                    ->url(route('boutique.sales.receipt', $sale), shouldOpenInNewTab: true),
            ])
            ->send();

        $this->reset(['cart', 'search', 'customerId', 'amountPaid', 'discount']);
        $this->paymentMethod = 'cash';
    }
}
