<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'public_uuid', 'order_id', 'gateway', 'status', 'gateway_reference',
    'card_brand', 'card_last_four', 'installments', 'amount', 'currency',
    'sanitized_payload', 'authorized_at', 'failed_at',
])]
class PaymentAttempt extends Model
{
    protected static function booted(): void
    {
        static::creating(fn (self $attempt) => $attempt->public_uuid ??= (string) Str::uuid());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'installments' => 'integer',
            'amount' => 'decimal:2',
            'sanitized_payload' => 'array',
            'authorized_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
