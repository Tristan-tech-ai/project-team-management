<?php

// Dibuat dari project_team_management_laravel.sql (tabel `project_member_divisions`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMemberDivision extends Model
{
    protected $table = 'project_member_divisions';

    protected $guarded = ['id'];

    public function projectMember(): BelongsTo
    {
        return $this->belongsTo(ProjectMember::class, 'project_member_id');
    }

    public function projectDivision(): BelongsTo
    {
        return $this->belongsTo(ProjectDivision::class, 'project_division_id');
    }
}
