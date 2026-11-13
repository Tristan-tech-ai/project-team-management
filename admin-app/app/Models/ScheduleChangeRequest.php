<?php

// Generated from project_team_management_laravel.sql (table `schedule_change_requests`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleChangeRequest extends Model
{
    protected $table = 'schedule_change_requests';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['previous_due_at' => 'datetime', 'proposed_due_at' => 'datetime', 'requested_at' => 'datetime', 'decided_at' => 'datetime', 'applied_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ProjectActivity::class, 'project_activity_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
