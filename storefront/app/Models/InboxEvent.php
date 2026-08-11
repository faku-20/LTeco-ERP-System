<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'event_uuid', 'body_hash', 'event_type', 'aggregate_uuid',
    'event_version', 'processed_at',
])]
class InboxEvent extends Model
{
    protected function casts(): array
    {
        return ['event_version' => 'integer', 'processed_at' => 'datetime'];
    }
}
