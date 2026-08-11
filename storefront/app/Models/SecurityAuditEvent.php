<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'public_uuid', 'user_id', 'actor_type', 'actor_reference', 'action',
    'object_type', 'object_reference', 'fields', 'ip_hash',
    'user_agent_summary', 'correlation_id', 'occurred_at',
])]
class SecurityAuditEvent extends Model
{
    protected static function booted(): void
    {
        static::creating(fn (self $event) => $event->public_uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['fields' => 'array', 'occurred_at' => 'datetime'];
    }
}
