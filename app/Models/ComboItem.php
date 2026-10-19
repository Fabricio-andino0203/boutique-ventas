<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ComboItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComboItem extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ComboItemFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'combo_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'eligible_variant_ids',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'eligible_variant_ids' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Combo, $this>
     */
    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante fija del componente. Nula = "se elige al vender".
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isChooseAtSale(): bool
    {
        return $this->product_variant_id === null;
    }

    /**
     * Variantes que el cliente puede elegir para este componente.
     * Si no se limitó, son todas las variantes activas del producto.
     *
     * @return Collection<int, ProductVariant>
     */
    public function eligibleVariants(): Collection
    {
        if ($this->product_variant_id !== null) {
            return $this->variant()->get();
        }

        return ProductVariant::query()
            ->where('product_id', $this->product_id)
            ->where('is_active', true)
            ->when(
                filled($this->eligible_variant_ids),
                fn (Builder $query) => $query->whereIn('id', $this->eligible_variant_ids)
            )
            ->orderBy('price')
            ->get();
    }
}
