<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'order_id', 'product_variant_id', 'name', 'quantity', 'unit_price', 'discount', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            if (blank($item->name) && $item->product_variant_id) {
                $variant = $item->variant()->with('product')->first();
                if ($variant) {
                    $item->name = $variant->name
                        ?: trim(($variant->product?->name ?? 'Producto').' '.($variant->size ?? '').' '.($variant->color ?? ''));
                }
            }

            if (blank($item->subtotal)) {
                $item->subtotal = (float) $item->unit_price * (int) $item->quantity;
            }
        });
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
