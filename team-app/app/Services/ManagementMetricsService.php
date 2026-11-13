<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Management metrics (Technical Guide 3.2, 8.4, 11.2) computed with aggregate queries, not per row (no N+1).
 * - Project progress = done main tasks / non-deleted main tasks (subtasks are not in the denominator).
 * - Overdue = due_at < now and status is not done, computed on the fly.
 * - Unique members (COUNT DISTINCT project_members.user_id) are separated from the number of assignments (task_assignees rows).
 * - Deviation = latest due_at compared with baseline_due_at; punctuality = finished before/after the approved deadline.
 */
class ManagementMetricsService
{
    public function kpi(User $user): array
    {
        $ids = Scope::projects($user)->select('projects.id');
        $active = DB::table('projects')->whereNull('deleted_at')->whereIn('id', $ids)->whereIn('status', ['planned', 'active', 'on_hold'])
            ->selectRaw('project_type, count(*) n')->groupBy('project_type')->pluck('n', 'project_type');
        $main = DB::table('tasks')->whereNull('deleted_at')->whereNull('parent_id')->whereIn('project_id', $ids);

        return [
            'active_projects' => (int) $active->sum(), 'per_type' => $active->map(fn ($n) => (int) $n)->all(),
            'main_tasks' => (clone $main)->count(),
            'running_tasks' => (clone $main)->where('status', 'in_progress')->count(),
            'overdue' => (clone $main)->where('status', '!=', 'done')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'review' => (clone $main)->where('status', 'review')->count(),
            'pending_schedules' => DB::table('schedule_change_requests')->whereIn('project_id', $ids)->where('status', 'pending')->count(),
            'activities_7_days' => DB::table('project_activities')->whereIn('project_id', $ids)->where('created_at', '>=', now()->subDays(7))->count(),
            'unique_members' => (int) DB::table('project_members')->whereIn('project_id', $ids)->where('is_active', 1)->distinct()->count('user_id'),
            'assignments' => DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->whereNull('tasks.deleted_at')->whereIn('tasks.project_id', $ids)->count(),
        ];
    }

    /**
     * Project monitoring: progress, unique members, divisions, overdue, baseline vs due, condition.
     * Condition: blocked = an unfinished activity with a "blocking" impact; delayed = the project due_at has passed or a main task is overdue;
     * at_risk = due_at moved from the baseline or a schedule request is pending; otherwise on_track. Finished/cancelled projects = completed/cancelled.
     *
     * @return Collection<int, object>
     */
    public function monitoring(User $user, array $filter = []): Collection
    {
        $q = Scope::projects($user)->from('projects')->whereNull('projects.deleted_at')
            ->leftJoin('companies', 'companies.id', '=', 'projects.company_id')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->leftJoin('users as pic', 'pic.id', '=', 'projects.pic_user_id')
            ->select('projects.id', 'projects.code', 'projects.name', 'projects.project_type', 'projects.status', 'projects.baseline_due_at', 'projects.due_at',
                'companies.name as company', 'clients.name as client', 'pic.name as pic')
            ->selectSub(DB::table('tasks')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->whereNull('parent_id')->whereNull('deleted_at'), 'main_tasks')
            ->selectSub(DB::table('tasks')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->whereNull('parent_id')->whereNull('deleted_at')->where('status', 'done'), 'done_tasks')
            ->selectSub(DB::table('tasks')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->whereNull('parent_id')->whereNull('deleted_at')->where('status', '!=', 'done')->where('due_at', '<', now()), 'overdue_tasks')
            ->selectSub(DB::table('project_members')->selectRaw('count(distinct user_id)')->whereColumn('project_id', 'projects.id')->where('is_active', 1), 'members')
            ->selectSub(DB::table('project_divisions')->selectRaw('count(distinct division_id)')->whereColumn('project_id', 'projects.id'), 'divisions')
            ->selectSub(DB::table('project_activities')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->where('impact_type', 'blocked')->where('status', '!=', 'done'), 'blockers')
            ->selectSub(DB::table('schedule_change_requests')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->where('status', 'pending'), 'pending_schedules')
            ->orderByRaw("field(projects.status,'active','on_hold','planned','completed','cancelled')")->orderBy('projects.due_at');
        foreach (['project_type', 'status', 'company_id', 'client_id', 'pic_user_id'] as $k) {
            if (! empty($filter[$k])) {
                $q->where('projects.'.$k, $filter[$k]);
            }
        }

        return $q->get()->map(function ($p) {
            $p->progress = $p->main_tasks ? (int) round($p->done_tasks * 100 / $p->main_tasks) : 0;
            $p->condition = self::condition($p);

            return $p;
        });
    }

    public static function condition(object $p): string
    {
        if (in_array($p->status, ['completed', 'cancelled'], true)) {
            return $p->status;
        }
        if ((int) $p->blockers > 0) {
            return 'blocked';
        }
        if (($p->due_at && \Illuminate\Support\Carbon::parse($p->due_at)->isPast()) || (int) $p->overdue_tasks > 0) {
            return 'delayed';
        }
        if (($p->baseline_due_at && $p->due_at && (string) $p->baseline_due_at !== (string) $p->due_at) || (int) $p->pending_schedules > 0) {
            return 'at_risk';
        }

        return 'on_track';
    }

    public const CONDITIONS = ['on_track' => 'Sesuai jadwal', 'at_risk' => 'Perlu perhatian', 'delayed' => 'Terlambat', 'blocked' => 'Terhambat', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public const CONDITION_CLASSES = ['on_track' => 'ok', 'at_risk' => 'risk', 'delayed' => 'late', 'blocked' => 'blocked', 'completed' => 'done', 'cancelled' => 'neutral'];

    /** Workload per member: active assignments, overdue, done, logged work minutes, minutes of attended activities. */
    public function memberWorkload(User $user, ?int $divisionId = null): Collection
    {
        $ids = Scope::projects($user)->select('projects.id');
        $q = DB::table('users')->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')->where('user_profiles.is_active', 1)
            ->whereIn('users.id', DB::table('project_members')->select('user_id')->whereIn('project_id', $ids)->where('is_active', 1))
            ->select('users.id', 'users.name')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->selectRaw('count(*)')->whereColumn('task_assignees.user_id', 'users.id')
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->whereIn('tasks.project_id', $ids), 'active')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->selectRaw('count(*)')->whereColumn('task_assignees.user_id', 'users.id')
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->where('tasks.due_at', '<', now())->whereIn('tasks.project_id', $ids), 'overdue')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->selectRaw('count(*)')->whereColumn('task_assignees.user_id', 'users.id')
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', 'done')->whereIn('tasks.project_id', $ids), 'done')
            ->selectSub(DB::table('project_members')->selectRaw('count(distinct project_id)')->whereColumn('project_members.user_id', 'users.id')->where('is_active', 1)->whereIn('project_id', $ids), 'project')
            ->selectSub(DB::table('task_time_entries')->join('tasks', 'tasks.id', '=', 'task_time_entries.task_id')->selectRaw('coalesce(sum(minutes_spent),0)')->whereColumn('task_time_entries.user_id', 'users.id')->whereIn('tasks.project_id', $ids), 'work_minutes')
            ->selectSub(DB::table('project_activity_participants')->join('project_activities', 'project_activities.id', '=', 'project_activity_participants.project_activity_id')
                ->selectRaw('coalesce(sum(project_activities.duration_minutes),0)')->whereColumn('project_activity_participants.user_id', 'users.id')->whereIn('project_activities.project_id', $ids), 'activity_minutes')
            ->orderByDesc('active')->orderBy('users.name');
        if ($divisionId) {
            $q->whereIn('users.id', DB::table('division_members')->select('user_id')->where('division_id', $divisionId)->where('is_active', 1));
        }

        return $q->get();
    }

    /** Summary per division: unique members in the division, projects involving the division, active tasks of division members. */
    public function perDivision(User $user): Collection
    {
        $ids = Scope::projects($user)->select('projects.id');

        return DB::table('divisions')->join('companies', 'companies.id', '=', 'divisions.company_id')->where('divisions.is_active', 1)
            // Scope: only divisions in companies visible to the user (the Super Admin sees all).
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->whereIn('divisions.company_id', Scope::filterCompanies($user)->keys()->all() ?: [0]))
            ->select('divisions.id', 'divisions.name', 'companies.name as company')
            ->selectSub(DB::table('division_members')->selectRaw('count(distinct user_id)')->whereColumn('division_id', 'divisions.id')->where('is_active', 1), 'members')
            ->selectSub(DB::table('project_divisions')->selectRaw('count(distinct project_id)')->whereColumn('division_id', 'divisions.id')->whereIn('project_id', $ids), 'project')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
                ->join('division_members', 'division_members.user_id', '=', 'task_assignees.user_id')
                ->selectRaw('count(distinct task_assignees.task_id)')->whereColumn('division_members.division_id', 'divisions.id')->where('division_members.is_active', 1)
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->whereIn('tasks.project_id', $ids), 'active_tasks')
            ->orderBy('companies.name')->orderBy('divisions.name')->get();
    }

    /** Schedule change trail for the operational report. */
    public function scheduleTrail(User $user, ?string $from = null, ?string $until = null): Collection
    {
        $q = DB::table('schedule_change_requests as s')->join('projects', 'projects.id', '=', 's.project_id')
            ->leftJoin('tasks', 'tasks.id', '=', 's.task_id')->leftJoin('users as r', 'r.id', '=', 's.requested_by')->leftJoin('users as d', 'd.id', '=', 's.decided_by')
            ->leftJoin('project_activities as a', 'a.id', '=', 's.project_activity_id')
            ->whereIn('s.project_id', Scope::projects($user)->select('projects.id'))
            ->select('s.id', 'projects.name as project', 'tasks.title as task', 's.previous_due_at', 's.proposed_due_at', 's.reason', 's.status', 's.requested_at', 's.decided_at',
                'r.name as requester', 'd.name as decider', 'a.title as activities',
                DB::raw('coalesce(tasks.baseline_due_at, projects.baseline_due_at) as baseline'))
            ->orderByDesc('s.requested_at');
        if ($from) {
            $q->where('s.requested_at', '>=', $from);
        }
        if ($until) {
            $q->where('s.requested_at', '<=', $until);
        }

        return $q->get();
    }

    /** Progress per project/period (used by the project pages). */
    public static function projectProgress(int $projectId, ?int $periodId = null): array
    {
        $q = DB::table('tasks')->where('project_id', $projectId)->whereNull('parent_id')->whereNull('deleted_at');
        if ($periodId) {
            $q->where('project_period_id', $periodId);
        }
        $total = (clone $q)->count();
        $done = (clone $q)->where('status', 'done')->count();

        return ['total' => $total, 'done' => $done, 'percent' => $total ? (int) round($done * 100 / $total) : 0];
    }
}
