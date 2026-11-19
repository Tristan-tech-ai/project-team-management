<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPeriod;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Support\CodeGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
    public function preview(?ProjectTemplate $template, ?Carbon $start = null, ?string $projectType = null, ?string $unit = null): array
    {
        if (! $template) {
            return [];
        }
        $start = ($start ?? now())->copy()->setTimezone(\App\Support\Dates::zone());
        // A recurring or ongoing project starts its first period at the start of the month (or week), like nextPeriod().
        if (($projectType ?? $template->project_type) !== 'one_time') {
            $start = $unit === 'week' ? $start->startOfWeek() : $start->startOfMonth();
        }
        $all = $template->tasks()->with('routine')->orderBy('sort_order')->orderBy('id')->get();
        $result = [];
        foreach ($all->whereNull('parent_id') as $t) {
            $result[] = [
                'title' => $t->title, 'task_type' => $t->task_type, 'estimated_minutes' => $t->estimated_minutes,
                'due_at' => $t->relative_due_days !== null && ! $t->routine ? $start->copy()->addDays($t->relative_due_days)->endOfDay() : null,
                'routine' => $t->routine ? trim(\App\Models\TemplateTaskRoutine::RULES[$t->routine->repeat_rule].' '.$t->routine->window()) : null,
                'subtasks' => $t->routine ? [] : $all->where('parent_id', $t->id)->pluck('title')->values()->all(),
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
                'code' => ($d['code'] ?? null) ?: self::codeFor($d['name']), 'name' => $d['name'], 'description' => $d['description'] ?? null,
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
                // "Lewati yang sudah lewat": a project that starts in the middle of its first period gets only the tasks still ahead.
                $skipBefore = ! empty($d['skip_past']) ? Carbon::instance($project->start_at)->setTimezone(\App\Support\Dates::zone())->startOfDay()->utc() : null;
                $this->generate($project, ProjectTemplate::query()->findOrFail($d['project_template_id']), $period, $by, [], $skipBefore);
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
                $this->generate($p, ProjectTemplate::query()->findOrFail($p->project_template_id), $period, $by, $this->handover($p, $latest));
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

    /**
     * Creates tasks + subtasks from a project template. Task PIC = project PIC, who automatically becomes an assignee.
     * A deadline "day N" is the end of that day (23.59 local time), so a task is not late on the morning it is due.
     * A routine task (template_task_routines) gets one checklist item per day of the period, each due at the end of its time window.
     */
    public function generate(Project $project, ProjectTemplate $template, ?ProjectPeriod $period, User $by, array $handover = [], ?Carbon $skipBefore = null): int
    {
        $zone = \App\Support\Dates::zone();
        $start = ($period ? Carbon::instance($period->starts_at) : Carbon::instance($project->start_at ?? now()))->setTimezone($zone);
        $all = $template->tasks()->with('routine')->orderBy('sort_order')->orderBy('id')->get();
        $total = 0;
        foreach ($all->whereNull('parent_id')->values() as $i => $tt) {
            $due = $tt->relative_due_days !== null ? $start->copy()->addDays($tt->relative_due_days)->endOfDay()->startOfMinute()->utc()
                : ($period ? Carbon::instance($period->ends_at) : $project->due_at);
            if ($skipBefore && ! $tt->routine && $due && Carbon::instance($due)->lt($skipBefore)) {
                continue;
            }
            $days = $tt->routine ? $this->routineDays($tt->routine, $start, $period, $project) : [];
            if ($days !== []) {
                $due = end($days)[1];
            }
            // The same task in the previous period keeps its people (Admin sosmed stays with Putri every month).
            [$pic, $people] = $handover[$tt->title] ?? [(int) $project->pic_user_id, []];
            $task = Task::create([
                'project_id' => $project->id, 'project_period_id' => $period?->id, 'parent_id' => null, 'pic_user_id' => $pic,
                'created_by' => $by->id, 'title' => $tt->title, 'description' => $tt->description, 'task_type' => $tt->task_type ?: 'general',
                'status' => 'backlog', 'priority' => 'normal', 'estimated_minutes' => $tt->estimated_minutes,
                'baseline_due_at' => $due, 'due_at' => $due, 'sort_order' => $i,
            ]);
            foreach (array_unique([$pic, ...$people]) as $uid) {
                $this->teamService->ensureAssignee($task, (int) $uid, $by);
            }
            TaskActivity::create(['task_id' => $task->id, 'user_id' => $by->id, 'activity_type' => 'created', 'description' => 'Dibuat dari template '.$template->name]);
            $total++;
            foreach ($days as $j => [$from, $until, $label]) {
                Task::create(['project_id' => $project->id, 'project_period_id' => $period?->id, 'parent_id' => $task->id, 'pic_user_id' => $pic,
                    'created_by' => $by->id, 'title' => $label, 'task_type' => $tt->task_type ?: 'general', 'status' => 'backlog',
                    'start_at' => $from, 'baseline_due_at' => $until, 'due_at' => $until, 'sort_order' => $j]);
                $total++;
            }
            foreach ($all->where('parent_id', $tt->id)->values() as $j => $st) {
                Task::create(['project_id' => $project->id, 'project_period_id' => $period?->id, 'parent_id' => $task->id, 'pic_user_id' => $pic,
                    'created_by' => $by->id, 'title' => $st->title, 'task_type' => $st->task_type ?: 'general', 'status' => 'backlog',
                    'estimated_minutes' => $st->estimated_minutes, 'sort_order' => $j]);
                $total++;
            }
        }

        return $total;
    }

    /**
     * PIC and assignees of each main task of the previous period, by title, kept only for people who are still active project members.
     *
     * @return array<string, array{0: int, 1: list<int>}>
     */
    private function handover(Project $project, ?ProjectPeriod $previous): array
    {
        if (! $previous) {
            return [];
        }
        $active = DB::table('project_members')->where('project_id', $project->id)->where('is_active', 1)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        $map = [];
        foreach (Task::query()->where('project_period_id', $previous->id)->whereNull('parent_id')->with('assignees:id')->orderBy('sort_order')->get() as $t) {
            if (isset($map[$t->title]) || ! in_array((int) $t->pic_user_id, $active, true)) {
                continue;
            }
            $map[$t->title] = [(int) $t->pic_user_id, array_values(array_intersect($t->assignees->pluck('id')->map(fn ($id) => (int) $id)->all(), $active))];
        }

        return $map;
    }

    /**
     * The days of a routine inside the period (or from the project start to its deadline, at most 62 days; 30 days without a deadline).
     *
     * @return list<array{0: Carbon, 1: Carbon, 2: string}> [start (UTC), due (UTC), checklist title such as "Senin, 1 Des 10.00-15.00"]
     */
    private function routineDays(\App\Models\TemplateTaskRoutine $routine, Carbon $start, ?ProjectPeriod $period, Project $project): array
    {
        $zone = \App\Support\Dates::zone();
        $first = $start->copy()->setTimezone($zone)->startOfDay();
        // A project that starts in the middle of its first period has no routine days before it existed.
        $projectStart = $project->start_at ? Carbon::instance($project->start_at)->setTimezone($zone)->startOfDay() : null;
        if ($projectStart && $projectStart->gt($first)) {
            $first = $projectStart;
        }
        $last = $period ? Carbon::instance($period->ends_at)->setTimezone($zone)->startOfDay()
            : ($project->due_at ? Carbon::instance($project->due_at)->setTimezone($zone)->startOfDay() : $first->copy()->addDays(29));
        if ($last->lt($first)) {
            $last = $first->copy();
        }
        [$fromTime, $untilTime] = [$routine->start_time ? substr($routine->start_time, 0, 5) : null, $routine->end_time ? substr($routine->end_time, 0, 5) : null];
        $window = $routine->window();
        $days = [];
        for ($d = $first->copy(); $d->lte($last) && count($days) < 62; $d->addDay()) {
            if ($routine->repeat_rule === 'weekdays' && $d->isWeekend()) {
                continue;
            }
            $from = $fromTime ? $d->copy()->setTimeFromTimeString($fromTime) : $d->copy();
            $until = $untilTime ? $d->copy()->setTimeFromTimeString($untilTime) : $d->copy()->endOfDay()->startOfMinute();
            $label = $d->locale('id')->translatedFormat('l, j M').($window !== '' ? ' '.$window : '');
            $days[] = [$from->copy()->utc(), $until->copy()->utc(), $label];
        }

        return $days;
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

    /** U4: a project code is made from its name like company and client codes (unique, soft-deleted projects included). */
    public static function codeFor(string $name): string
    {
        return CodeGenerator::unique($name, fn (string $code) => Project::withTrashed()->where('code', $code)->exists());
    }
}
