<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'event_uuid', 'aggregate_type', 'aggregate_uuid', 'event_type',
    'payload', 'idempotency_key', 'status', 'attempts', 'available_at',
    'processed_at', 'last_error',
])]
class OutboxEvent extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->event_uuid ??= (string) Str::uuid();
            $event->idempotency_key ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
