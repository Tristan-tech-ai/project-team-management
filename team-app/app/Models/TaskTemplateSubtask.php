<?php

// Dibuat dari project_team_management_laravel.sql (tabel `task_template_subtasks`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTemplateSubtask extends Model
{
    protected $table = 'task_template_subtasks';

    protected $guarded = ['id'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'task_template_id');
    }
}
