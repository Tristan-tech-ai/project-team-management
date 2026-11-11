<?php

// Dibuat dari project_team_management_laravel.sql (tabel `task_time_entries`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskTimeEntry extends Model
{
    protected $table = 'task_time_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
