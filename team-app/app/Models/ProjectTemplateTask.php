<?php

// Generated from project_team_management_laravel.sql (table `project_template_tasks`).

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTemplateTask extends Model
{
    protected $table = 'project_template_tasks';

    protected $guarded = ['id'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplateTask::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ProjectTemplateTask::class, 'parent_id');
    }
}
