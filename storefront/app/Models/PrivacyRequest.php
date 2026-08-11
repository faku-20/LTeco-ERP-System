<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'public_uuid', 'user_id', 'type', 'status', 'request_details_encrypted',
    'resolution_manifest', 'due_at', 'resolved_at',
    'resolved_by_panel_user_id', 'approved_by_panel_user_id',
])]
class PrivacyRequest extends Model
{
    protected static function booted(): void
    {
        static::creating(fn (self $request) => $request->public_uuid ??= (string) Str::uuid());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'request_details_encrypted' => 'encrypted:array',
            'resolution_manifest' => 'array',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
