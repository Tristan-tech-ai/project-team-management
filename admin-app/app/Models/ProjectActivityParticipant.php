<?php

// Dibuat dari project_team_management_laravel.sql (tabel `project_activity_participants`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectActivityParticipant extends Model
{
    protected $table = 'project_activity_participants';

    protected $guarded = ['id'];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(ProjectActivity::class, 'project_activity_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
