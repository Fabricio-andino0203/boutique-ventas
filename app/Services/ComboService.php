<?php

namespace App\Services;

use App\Models\Combo;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Alta/edición de combos. Cada combo se respalda con un Product(type='combo')
 * y una única "variante combo" para poder venderse, buscarse e imprimirse con
 * la misma maquinaria que el resto del catálogo. Aquí se crea ese respaldo, se
 * guardan los componentes y se mantiene la variante combo sincronizada.
 */
class ComboService
{
    public function __construct(private ComboPricing $pricing) {}

    /**
     * @param  array<string, mixed>  $data  Campos del combo + 'items' (componentes).
     */
    public function create(array $data): Combo
    {
        return DB::transaction(function () use ($data): Combo {
            $product = Product::create([
                'type' => 'combo',
                'name' => $data['name'],
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'default_price' => $data['price'] ?? 0,
                'manage_stock' => ($data['stock_mode'] ?? 'derived') === 'own',
                'min_stock' => (int) ($data['min_stock'] ?? 0),
                'tax_type' => 'none',
                'is_active' => $data['is_active'] ?? true,
            ]);

            $variant = $product->variants()->create([
                'name' => $data['name'],
                'price' => $data['price'] ?? 0,
                'cost' => 0,
                'stock_quantity' => 0,
                'min_stock' => (int) ($data['min_stock'] ?? 0),
                'barcode' => $data['barcode'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $combo = Combo::create([
                'product_id' => $product->id,
                'product_variant_id' => $variant->id,
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'name' => $data['name'],
                'image_path' => $data['image_path'] ?? null,
                'pricing_mode' => $data['pricing_mode'] ?? 'cheapest',
                'price' => $data['price'] ?? 0,
                'base_price' => $data['base_price'] ?? null,
                'stock_mode' => $data['stock_mode'] ?? 'derived',
                'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
                'min_stock' => (int) ($data['min_stock'] ?? 0),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->syncItems($combo, $data['items'] ?? []);
            $this->syncBackingVariant($combo);

            return $combo;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Combo $combo, array $data): Combo
    {
        return DB::transaction(function () use ($combo, $data): Combo {
            $combo->update([
                'category_id' => $data['category_id'] ?? null,
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'name' => $data['name'],
                'image_path' => $data['image_path'] ?? $combo->image_path,
                'pricing_mode' => $data['pricing_mode'] ?? $combo->pricing_mode,
                'price' => $data['price'] ?? $combo->price,
                'base_price' => $data['base_price'] ?? null,
                'stock_mode' => $data['stock_mode'] ?? $combo->stock_mode,
                'stock_quantity' => (int) ($data['stock_quantity'] ?? $combo->stock_quantity),
                'min_stock' => (int) ($data['min_stock'] ?? $combo->min_stock),
                'is_active' => $data['is_active'] ?? $combo->is_active,
            ]);

            $combo->product?->update([
                'name' => $combo->name,
                'category_id' => $combo->category_id,
                'subcategory_id' => $combo->subcategory_id,
                'image_path' => $combo->image_path,
                'default_price' => $combo->price,
                'manage_stock' => $combo->stock_mode === 'own',
                'min_stock' => $combo->min_stock,
                'is_active' => $combo->is_active,
            ]);

            if (array_key_exists('items', $data)) {
                $combo->items()->delete();
                $this->syncItems($combo, $data['items']);
            }

            $this->syncBackingVariant($combo);

            return $combo;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Combo $combo, array $items): void
    {
        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $chooseAtSale = (bool) ($item['choose_at_sale'] ?? false);
            $variantId = $chooseAtSale ? null : ($item['product_variant_id'] ?? null);

            // Producto simple (una sola variante): se fija esa variante en vez de
            // dejarla como "a elegir".
            if (! $chooseAtSale && $variantId === null) {
                $variantId = ProductVariant::query()
                    ->where('product_id', $item['product_id'])
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->value('id');
            }

            $combo->items()->create([
                'product_id' => $item['product_id'],
                'product_variant_id' => $variantId,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'eligible_variant_ids' => $chooseAtSale ? ($item['eligible_variant_ids'] ?? null) : null,
            ]);
        }
    }

    /**
     * Mantiene la variante combo alineada con el combo: precio, costo (suma de
     * los componentes) y stock (el propio, o el derivado calculado).
     */
    public function syncBackingVariant(Combo $combo): void
    {
        $combo->loadMissing('items', 'variant');

        $variant = $combo->variant;

        if ($variant === null) {
            return;
        }

        $variant->update([
            'name' => $combo->name,
            'price' => $combo->price,
            'cost' => $this->representativeCost($combo),
            'stock_quantity' => $combo->stock_mode === 'own'
                ? (int) $combo->stock_quantity
                : $this->derivedAvailability($combo),
            'min_stock' => $combo->min_stock,
            'is_active' => $combo->is_active,
        ]);
    }

    /** Costo de referencia del combo: suma de costos representativos × cantidad. */
    public function representativeCost(Combo $combo): float
    {
        $combo->loadMissing('items');

        $total = 0.0;

        foreach ($combo->items as $item) {
            $cost = $item->eligibleVariants()->min('cost');
            $total += (float) $cost * max(1, (int) $item->quantity);
        }

        return round($total, 2);
    }

    /**
     * Cuántos combos se pueden armar con el stock actual de los componentes
     * (para mostrar/vender combos de stock derivado).
     */
    public function derivedAvailability(Combo $combo): int
    {
        $combo->loadMissing('items');

        if ($combo->items->isEmpty()) {
            return 0;
        }

        $available = PHP_INT_MAX;

        foreach ($combo->items as $item) {
            $quantity = max(1, (int) $item->quantity);
            $stock = (int) $item->eligibleVariants()->max('stock_quantity');
            $available = min($available, intdiv($stock, $quantity));
        }

        return $available === PHP_INT_MAX ? 0 : max(0, $available);
    }
}
