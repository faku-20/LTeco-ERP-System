<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['public_uuid', 'user_id', 'guest_token_hash', 'status', 'currency', 'expires_at', 'guest_expires_at'])]
class Cart extends Model
{
    protected static function booted(): void
    {
        static::creating(fn (self $cart) => $cart->public_uuid ??= (string) Str::uuid());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'guest_expires_at' => 'datetime'];
    }
}
