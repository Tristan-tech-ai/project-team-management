<?php

// Generated from project_team_management_laravel.sql (table `system_settings`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $guarded = ['id'];

    /** setting_value is a JSON column in the module SQL: text values are stored as JSON strings and read back as they are. */
    protected $casts = ['setting_value' => 'json'];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
