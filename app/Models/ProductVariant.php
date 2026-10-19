<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'product_id',
        'name',
        'options',
        'sku',
        'barcode',
        'size',
        'color',
        'model',
        'price',
        'cost',
        'wholesale_price',
        'stock_quantity',
        'min_stock',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
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
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
