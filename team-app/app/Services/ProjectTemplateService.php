<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPeriod;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a project from a template (Technical Guide 8.1 and 8.5): project, divisions, unique members (the PIC is automatically a member),
 * the first period for recurring/ongoing, then tasks + subtasks from the template, all in ONE transaction.
 * A failure at any step = everything is rolled back.
 */
class ProjectTemplateService
{
    public function __construct(private ProjectTeamService $teamService) {}

    /** Preview of the tasks/subtasks that will be created, without writing anything. */
    public function preview(?ProjectTemplate $template, ?Carbon $start = null): array
    {
        if (! $template) {
            return [];
        }
        $start ??= now();
        $all = $template->tasks()->orderBy('sort_order')->orderBy('id')->get();
        $result = [];
        foreach ($all->whereNull('parent_id') as $t) {
            $result[] = [
                'title' => $t->title, 'task_type' => $t->task_type, 'estimated_minutes' => $t->estimated_minutes,
                'due_at' => $t->relative_due_days !== null ? $start->copy()->addDays($t->relative_due_days) : null,
                'subtasks' => $all->where('parent_id', $t->id)->pluck('title')->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * @param  array{company_id:int, client_id:?int, name:string, code:?string, description:?string, project_type:string, status:?string,
     *               priority:?string, pic_user_id:int, start_at:?Carbon, due_at:?Carbon, recurrence_unit:?string, recurrence_interval:?int,
     *               project_template_id:?int, divisions:array<int>, members:array<int>, notes:?string}  $d
     */
    public function createProject(array $d, User $by): Project
    {
        return DB::transaction(function () use ($d, $by) {
            $project = Project::create([
                'company_id' => $d['company_id'], 'client_id' => $d['client_id'] ?? null, 'project_template_id' => $d['project_template_id'] ?? null,
                'pic_user_id' => $d['pic_user_id'], 'created_by' => $by->id,
                'code' => $d['code'] ?: $this->newCode(), 'name' => $d['name'], 'description' => $d['description'] ?? null,
                'project_type' => $d['project_type'], 'status' => $d['status'] ?? 'active', 'priority' => $d['priority'] ?? 'normal',
                'start_at' => $d['start_at'] ?? now(), 'baseline_due_at' => $d['due_at'] ?? null, 'due_at' => $d['due_at'] ?? null,
                'recurrence_unit' => $d['project_type'] === 'one_time' ? null : ($d['recurrence_unit'] ?? 'month'),
                'recurrence_interval' => $d['project_type'] === 'one_time' ? null : ($d['recurrence_interval'] ?? 1),
                'notes' => $d['notes'] ?? null,
            ]);
            $this->teamService->setTeam($project, $d['divisions'] ?? [], $d['members'] ?? [], $by);

            $period = null;
            if ($project->project_type !== 'one_time') {
                $period = $this->nextPeriod($project, $by, Carbon::instance($project->start_at));
            }
            if (! empty($d['project_template_id'])) {
                $this->generate($project, ProjectTemplate::query()->findOrFail($d['project_template_id']), $period, $by);
            }

            return $project->refresh();
        });
    }

    /**
     * Opens a new period for a recurring/ongoing project (manual, section 8.5 and SQL note C).
     * The parent project keeps running; tasks of the previous period stay in that period.
     */
    public function openPeriod(Project $project, User $by, ?Carbon $start = null, bool $generate = true): ProjectPeriod
    {
        if ($project->project_type === 'one_time') {
            throw ValidationException::withMessages(['period' => 'Project sekali jadi tidak punya periode.']);
        }

        return DB::transaction(function () use ($project, $by, $start, $generate) {
            $p = Project::query()->lockForUpdate()->findOrFail($project->id);
            $latest = $p->periods()->orderByDesc('starts_at')->first();
            $start ??= $latest ? Carbon::instance($latest->ends_at)->addSecond() : now();
            if ($latest && $latest->status === 'active') {
                $latest->update(['status' => 'closed', 'completed_at' => now()]);
            }
            $period = $this->nextPeriod($p, $by, $start);
            if ($generate && $p->project_template_id) {
                $this->generate($p, ProjectTemplate::query()->findOrFail($p->project_template_id), $period, $by);
            }

            return $period;
        });
    }

    private function nextPeriod(Project $project, User $by, Carbon $start): ProjectPeriod
    {
        $unit = $project->recurrence_unit ?: 'month';
        $n = max(1, (int) ($project->recurrence_interval ?: 1));
        $local = $start->copy()->setTimezone(\App\Support\Dates::zone());
        if ($unit === 'week') {
            $periodStart = $local->copy()->startOfWeek();
            $end = $periodStart->copy()->addWeeks($n)->subSecond();
            $code = $periodStart->format('o').'-W'.$periodStart->format('W');
            $name = 'Minggu '.$periodStart->format('W').' '.$periodStart->format('o');
        } else {
            $periodStart = $local->copy()->startOfMonth();
            $end = $periodStart->copy()->addMonthsNoOverflow($n)->subSecond();
            $code = $periodStart->format('Y-m');
            $name = 'Periode '.\App\Support\Dates::date($periodStart).' sampai '.\App\Support\Dates::date($end);
        }
        if ($project->periods()->where('period_code', $code)->exists()) {
            throw ValidationException::withMessages(['period' => "Periode {$code} sudah ada di project ini."]);
        }

        return ProjectPeriod::create(['project_id' => $project->id, 'period_code' => $code, 'name' => $name,
            'starts_at' => $periodStart->utc(), 'ends_at' => $end->utc(), 'status' => 'active', 'created_by' => $by->id]);
    }

    /** Creates tasks + subtasks from a project template. Task PIC = project PIC, who automatically becomes an assignee. */
    public function generate(Project $project, ProjectTemplate $template, ?ProjectPeriod $period, User $by): int
    {
        $start = $period ? Carbon::instance($period->starts_at) : Carbon::instance($project->start_at ?? now());
        $all = $template->tasks()->orderBy('sort_order')->orderBy('id')->get();
        $total = 0;
        foreach ($all->whereNull('parent_id')->values() as $i => $tt) {
            $due = $tt->relative_due_days !== null ? $start->copy()->addDays($tt->relative_due_days) : ($period ? Carbon::instance($period->ends_at) : $project->due_at);
            $task = Task::create([
                'project_id' => $project->id, 'project_period_id' => $period?->id, 'parent_id' => null, 'pic_user_id' => $project->pic_user_id,
                'created_by' => $by->id, 'title' => $tt->title, 'description' => $tt->description, 'task_type' => $tt->task_type ?: 'general',
                'status' => 'backlog', 'priority' => 'normal', 'estimated_minutes' => $tt->estimated_minutes,
                'baseline_due_at' => $due, 'due_at' => $due, 'sort_order' => $i,
            ]);
            $this->teamService->ensureAssignee($task, (int) $project->pic_user_id, $by);
            TaskActivity::create(['task_id' => $task->id, 'user_id' => $by->id, 'activity_type' => 'created', 'description' => 'Dibuat dari template '.$template->name]);
            $total++;
            foreach ($all->where('parent_id', $tt->id)->values() as $j => $st) {
                Task::create(['project_id' => $project->id, 'project_period_id' => $period?->id, 'parent_id' => $task->id, 'pic_user_id' => $project->pic_user_id,
                    'created_by' => $by->id, 'title' => $st->title, 'task_type' => $st->task_type ?: 'general', 'status' => 'backlog',
                    'estimated_minutes' => $st->estimated_minutes, 'sort_order' => $j]);
                $total++;
            }
        }

        return $total;
    }

    /** Applies a standalone task template (task + checklist) to a project. */
    public function applyTaskTemplate(Project $project, TaskTemplate $tt, array $content, User $by): Task
    {
        return DB::transaction(function () use ($project, $tt, $content, $by) {
            $task = Task::create($content + ['project_id' => $project->id, 'created_by' => $by->id, 'task_template_id' => $tt->id,
                'task_type' => $content['task_type'] ?? $tt->task_type, 'estimated_minutes' => $content['estimated_minutes'] ?? $tt->default_estimated_minutes]);
            foreach ($tt->subtasks()->orderBy('sort_order')->get() as $j => $s) {
                Task::create(['project_id' => $project->id, 'project_period_id' => $task->project_period_id, 'parent_id' => $task->id,
                    'pic_user_id' => $task->pic_user_id, 'created_by' => $by->id, 'title' => $s->title, 'status' => 'backlog', 'sort_order' => $j,
                    'estimated_minutes' => $s->estimated_minutes]);
            }

            return $task;
        });
    }

    private function newCode(): string
    {
        do {
            $code = 'PRJ-'.now()->format('ym').'-'.Str::upper(Str::random(4));
        } while (Project::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
