<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'purpose', 'document_version', 'document_hash',
    'accepted_at', 'ip_hash', 'user_agent_summary', 'withdrawn_at',
])]
class ConsentRecord extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }
}
