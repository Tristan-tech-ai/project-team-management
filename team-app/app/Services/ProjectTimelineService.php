<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ScheduleChangeRequest;
use App\Models\Task;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Project timeline (Technical Guide 7.3 prototype): finished main tasks, meetings, visits, revisions, blockers,
 * and deadline change requests in one chronological list. One paginated UNION query picks the page,
 * then each kind is loaded in a single query with its relations (no query per row).
 */
class ProjectTimelineService
{
    public const PER_PAGE = 20;

    /** Chronological order like the prototype; without ?page= the last page opens, so the newest events are visible after saving. */
    public function page(Project $project, ?int $pageNumber = null, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $activities = DB::table('project_activities')->where('project_id', $project->id)
            ->selectRaw("'activity' as kind, id, coalesce(occurred_at, scheduled_at, created_at) as happened_at");
        $doneTasks = DB::table('tasks')->where('project_id', $project->id)->whereNull('parent_id')->whereNull('deleted_at')
            ->where('status', 'done')->whereNotNull('completed_at')
            ->selectRaw("'task_done' as kind, id, completed_at as happened_at");
        $requests = DB::table('schedule_change_requests')->where('project_id', $project->id)
            ->selectRaw("'schedule_change' as kind, id, requested_at as happened_at");
        $union = DB::query()->fromSub($activities->unionAll($doneTasks)->unionAll($requests), 'timeline');
        $pageNumber ??= max(1, (int) ceil((clone $union)->count() / $perPage));
        $page = $union->orderBy('happened_at')->orderBy('kind')->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $pageNumber)->withQueryString();

        $ids = collect($page->items())->groupBy('kind')->map(fn ($rows) => $rows->pluck('id')->all());
        $models = [
            'activity' => ProjectActivity::query()->whereIn('id', $ids->get('activity', []))
                ->with(['creator:id,name', 'participants:id,name', 'task:id,title'])->get()->keyBy('id'),
            'task_done' => Task::query()->whereIn('id', $ids->get('task_done', []))
                ->with(['completer:id,name', 'pic:id,name'])->get()->keyBy('id'),
            'schedule_change' => ScheduleChangeRequest::query()->whereIn('id', $ids->get('schedule_change', []))
                ->with(['project:id,name,pic_user_id,company_id,baseline_due_at,due_at', 'task:id,project_id,pic_user_id,title,baseline_due_at,due_at', 'requester:id,name', 'decider:id,name', 'activity:id,title'])->get()->keyBy('id'),
        ];

        return $page->setCollection(collect($page->items())->map(fn ($row) => (object) [
            'kind' => $row->kind,
            'at' => $row->happened_at,
            'model' => $models[$row->kind]->get($row->id),
        ])->filter(fn ($item) => $item->model !== null)->values());
    }

    /** Schedule condition card: initial target, current deadline, and whether a request waits for a decision. */
    public function condition(Project $project): object
    {
        $pending = ScheduleChangeRequest::query()->where('project_id', $project->id)->where('status', 'pending')->count();
        $shiftDays = $project->baseline_due_at && $project->due_at && ! $project->baseline_due_at->equalTo($project->due_at)
            ? (int) round($project->baseline_due_at->diffInDays($project->due_at, false))
            : 0;

        return (object) [
            'baseline' => $project->baseline_due_at,
            'current' => $project->due_at,
            'pending' => $pending,
            'shift_days' => $shiftDays,
            'status' => $pending > 0 ? 'pending' : ($shiftDays !== 0 ? 'shifted' : 'on_target'),
        ];
    }
}
