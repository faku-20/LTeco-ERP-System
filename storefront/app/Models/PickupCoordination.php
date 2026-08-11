<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'status', 'agreed_at', 'reservation_expires_at',
    'panel_user_id', 'version',
])]
class PickupCoordination extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'agreed_at' => 'datetime',
            'reservation_expires_at' => 'datetime',
            'panel_user_id' => 'integer',
            'version' => 'integer',
        ];
    }
}
