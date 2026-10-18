<?php

namespace App\Filament\Boutique\Pages;

use App\Actions\RegisterSale;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\ProductVariant;
use BackedEnum;
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

    protected static ?int $navigationSort = -10;

    /**
     * @var array<int, array{id:int, label:string, price:float, qty:int, stock:int}>
     */
    public array $cart = [];

    public string $search = '';

    public ?int $customerId = null;

    public string $paymentMethod = 'cash';

    public float $amountPaid = 0;

    public float $discount = 0;

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

        $term = '%'.trim($this->search).'%';

        return ProductVariant::query()
            ->where('is_active', true)
            ->with('product')
            ->where(function ($query) use ($term): void {
                $query->where('sku', 'ilike', $term)
                    ->orWhere('barcode', 'ilike', $term)
                    ->orWhere('color', 'ilike', $term)
                    ->orWhere('size', 'ilike', $term)
                    ->orWhereHas('product', fn ($q) => $q->where('name', 'ilike', $term));
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
        if (isset($this->cart[$variantId])) {
            $this->incrementQty($variantId);

            return;
        }

        $variant = ProductVariant::query()->with('product')->find($variantId);

        if ($variant === null || $variant->stock_quantity < 1) {
            Notification::make()->title('Sin existencias')->danger()->send();

            return;
        }

        $label = trim(($variant->product->name ?? 'Producto').' '.collect([$variant->size, $variant->color, $variant->model])->filter()->implode(' / '));

        $this->cart[$variantId] = [
            'id' => $variant->id,
            'label' => $label,
            'price' => (float) $variant->price,
            'qty' => 1,
            'stock' => (int) $variant->stock_quantity,
        ];

        $this->search = '';
    }

    public function incrementQty(int $variantId): void
    {
        if (! isset($this->cart[$variantId])) {
            return;
        }

        if ($this->cart[$variantId]['qty'] < $this->cart[$variantId]['stock']) {
            $this->cart[$variantId]['qty']++;
        } else {
            Notification::make()->title('Alcanzaste el stock disponible')->warning()->send();
        }
    }

    public function decrementQty(int $variantId): void
    {
        if (! isset($this->cart[$variantId])) {
            return;
        }

        $this->cart[$variantId]['qty']--;

        if ($this->cart[$variantId]['qty'] < 1) {
            unset($this->cart[$variantId]);
        }
    }

    public function removeFromCart(int $variantId): void
    {
        unset($this->cart[$variantId]);
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
            ->map(fn (array $item): array => [
                'variant_id' => $item['id'],
                'quantity' => $item['qty'],
                'unit_price' => $item['price'],
            ])
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
            ->send();

        $this->reset(['cart', 'search', 'customerId', 'amountPaid', 'discount']);
        $this->paymentMethod = 'cash';
    }
}
