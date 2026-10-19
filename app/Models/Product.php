<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'subcategory_id',
        'supplier_id',
        'brand_id',
        'type',
        'name',
        'code',
        'barcode',
        'unit',
        'description',
        'image_path',
        'gallery',
        'default_price',
        'default_cost',
        'wholesale_price',
        'min_stock',
        'allow_discount',
        'manage_stock',
        'allow_negative_stock',
        'variable_price',
        'propagate_main_image',
        'tax_type',
        'tax_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'default_price' => 'decimal:2',
            'default_cost' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'min_stock' => 'integer',
            'allow_discount' => 'boolean',
            'manage_stock' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'variable_price' => 'boolean',
            'propagate_main_image' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }
}
