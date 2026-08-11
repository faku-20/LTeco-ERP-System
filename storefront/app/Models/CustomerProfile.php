<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'customer_type', 'legal_name', 'phone_encrypted',
    'cedula_encrypted', 'cedula_blind_index', 'rut_encrypted',
    'rut_blind_index', 'panel_customer_id', 'status',
])]
class CustomerProfile extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'phone_encrypted' => 'encrypted',
            'cedula_encrypted' => 'encrypted',
            'rut_encrypted' => 'encrypted',
            'panel_customer_id' => 'integer',
        ];
    }
}
