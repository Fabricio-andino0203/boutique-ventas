<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ComboFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Combo extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ComboFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'product_variant_id',
        'category_id',
        'subcategory_id',
        'name',
        'image_path',
        'pricing_mode',
        'price',
        'base_price',
        'stock_mode',
        'stock_quantity',
        'min_stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'base_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variante combo que respalda la venta (línea del recibo y, si es propio, el stock).
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    /**
     * @return HasMany<ComboItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    public function isDerivedStock(): bool
    {
        return $this->stock_mode === 'derived';
    }
}
