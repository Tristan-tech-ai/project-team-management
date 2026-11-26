<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Per-project settings outside the module SQL (table project_settings, admin-app migration). One row per project at most. */
class ProjectSetting extends Model
{
    protected $table = 'project_settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['result_optional' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
