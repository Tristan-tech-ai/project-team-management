<?php

// Generated from project_team_management_laravel.sql (table `project_members`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProjectMember extends Model
{
    protected $table = 'project_members';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'joined_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function projectDivisions(): BelongsToMany
    {
        return $this->belongsToMany(ProjectDivision::class, 'project_member_divisions', 'project_member_id', 'project_division_id')->withTimestamps();
    }
}
