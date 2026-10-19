<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'number', 'customer_id', 'user_id', 'type', 'status', 'payment_status',
        'origin', 'subtotal', 'discount_type', 'discount_value', 'discount_amount', 'tax', 'total',
        'paid', 'estimated_at', 'delivery_address', 'recipient', 'phone', 'notes', 'sale_id',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'estimated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            if (blank($order->number)) {
                $order->number = 'P-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    /** Deuda pendiente. */
    public function getDueAttribute(): float
    {
        return max(0, (float) $this->total - (float) $this->paid);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<OrderMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(OrderMovement::class);
    }
}
