<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'cart_id', 'variant_id', 'quantity', 'model', 'battery_ah',
    'color', 'expected_gross', 'currency', 'catalog_version',
])]
class CartItem extends Model
{
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    protected function casts(): array
    {
        return ['expected_gross' => 'decimal:2', 'battery_ah' => 'integer', 'quantity' => 'integer'];
    }
}
