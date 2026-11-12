<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu aksi yang mengubah data (Petunjuk Teknis 4.1: semua aksi dicatat). Hanya ditambah, tidak diubah. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['input' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
