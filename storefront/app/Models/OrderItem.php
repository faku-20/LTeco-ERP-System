<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'vehicle_id', 'product_id', 'model', 'battery_ah',
    'color', 'gross', 'vat_included', 'currency', 'vehicle_snapshot',
])]
class OrderItem extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'battery_ah' => 'integer',
            'gross' => 'decimal:2',
            'vat_included' => 'decimal:2',
            'vehicle_snapshot' => 'array',
        ];
    }
}
