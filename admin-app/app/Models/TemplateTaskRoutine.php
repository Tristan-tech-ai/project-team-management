<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A template task that repeats every day (or every weekday) inside its project or period, optionally in a time window. */
class TemplateTaskRoutine extends Model
{
    public const RULES = ['daily' => 'Setiap hari', 'weekdays' => 'Setiap hari kerja (Sen-Jum)'];

    protected $table = 'template_task_routines';

    protected $guarded = ['id'];

    public function templateTask(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplateTask::class, 'project_template_task_id');
    }

    /** "10.00-15.00", or '' without a time window. */
    public function window(): string
    {
        $f = fn (?string $t) => $t ? str_replace(':', '.', substr($t, 0, 5)) : null;

        return $this->start_time && $this->end_time ? $f($this->start_time).'-'.$f($this->end_time) : '';
    }
}
