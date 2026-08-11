<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'type', 'line1_encrypted', 'line2_encrypted',
    'city_encrypted', 'department_encrypted', 'postal_code_encrypted',
    'country', 'is_primary',
])]
class CustomerAddress extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'line1_encrypted' => 'encrypted',
            'line2_encrypted' => 'encrypted',
            'city_encrypted' => 'encrypted',
            'department_encrypted' => 'encrypted',
            'postal_code_encrypted' => 'encrypted',
            'is_primary' => 'boolean',
        ];
    }
}
