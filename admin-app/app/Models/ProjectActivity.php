<?php

// Dibuat dari project_team_management_laravel.sql (tabel `project_activities`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectActivity extends Model
{
    protected $table = 'project_activities';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'occurred_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_activity_participants', 'project_activity_id', 'user_id')->withTimestamps();
    }

    public function scheduleChanges(): HasMany
    {
        return $this->hasMany(ScheduleChangeRequest::class, 'project_activity_id');
    }
}
