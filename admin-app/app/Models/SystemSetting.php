<?php

// Dibuat dari project_team_management_laravel.sql (tabel `system_settings`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $guarded = ['id'];

    /** Kolom setting_value bertipe JSON di SQL modul: nilai teks disimpan sebagai string JSON dan dibaca kembali apa adanya. */
    protected $casts = ['setting_value' => 'json'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
