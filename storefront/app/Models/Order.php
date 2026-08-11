<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'public_uuid', 'user_id', 'cart_id', 'status', 'payment_method',
    'currency', 'subtotal', 'discount', 'vat_included', 'total',
    'commercial_terms_version', 'billing_snapshot_encrypted',
    'terms_snapshot', 'panel_reservation_id', 'panel_order_id', 'panel_order_number', 'panel_sale_id',
    'lock_version', 'expires_at', 'paid_at', 'delivered_at',
])]
class Order extends Model
{
    protected static function booted(): void
    {
        static::creating(fn (self $order) => $order->public_uuid ??= (string) Str::uuid());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function pickupCoordinations(): HasMany
    {
        return $this->hasMany(PickupCoordination::class);
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'vat_included' => 'decimal:2',
            'total' => 'decimal:2',
            'billing_snapshot_encrypted' => 'encrypted:array',
            'terms_snapshot' => 'array',
            'lock_version' => 'integer',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
