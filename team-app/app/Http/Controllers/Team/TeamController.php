<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Shared\TaskListFeed;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\TaskTemplate;
use App\Support\ProjectPeople;
use App\Services\ManagementMetricsService;
use App\Services\ProjectTeamService;
use App\Services\Scope;
use App\Services\TaskWorkflowService;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Team App pages (Technical Guide 5.2). Every action uses the shared endpoints (Shared\TaskActionController,
 * Shared\OperationsController), so status, permission, and deadline rules are exactly the same as in the Admin App.
 */
class TeamController extends Controller
{
    /** My Summary: today's tasks, overdue, review, quick add. */
    public function home(Request $request): View
    {
        $u = $request->user();
        $ownTasks = $this->ownTasks($u);
        $zone = Dates::zone();
        $today = [Carbon::now($zone)->startOfDay()->utc(), Carbon::now($zone)->endOfDay()->utc()];

        return view('team.home', [
            'counts' => (clone $ownTasks)->selectRaw('tasks.status, count(*) n')->groupBy('tasks.status')->pluck('n', 'status'),
            'today' => (clone $ownTasks)->where('tasks.status', '!=', 'done')->whereBetween('tasks.due_at', $today)->with(['project:id,name'])->withCount($this->subtaskCounts())->orderBy('tasks.due_at')->get(),
            'overdue' => (clone $ownTasks)->where('tasks.status', '!=', 'done')->where('tasks.due_at', '<', now())->with(['project:id,name'])->withCount($this->subtaskCounts())->orderBy('tasks.due_at')->limit(5)->get(),
            'inReview' => (clone $ownTasks)->where('tasks.status', 'review')->with(['project:id,name'])->withCount($this->subtaskCounts())->limit(5)->get(),
            'revision' => TaskSubmission::query()->where('submitted_by', $u->id)->where('status', 'revision')->where('reviewed_at', '>=', now()->subDays(7))
                ->with('task:id,title,status')->latest('reviewed_at')->limit(3)->get()->filter(fn ($s) => $s->task?->status === 'in_progress'),
            'pendingForMe' => $u->hasPermission('tasks.review') ? $this->reviewQueue($u)->count() : 0,
            'projects' => Scope::memberProjects($u)->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->limit(4)->get(['id', 'name', 'project_type']),
        ]);
    }

    /** My Tasks: compact cards, project filter, deadline order (prototype 7.4). */
    public function tasks(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tasks.view'), 403);
        $u = $request->user();
        $q = $this->ownTasks($u)->with(['project:id,name', 'assignees:id,name', 'pic:id,name'])->withCount($this->subtaskCounts());
        if ($request->filled('project_id')) {
            $q->where('tasks.project_id', $request->integer('project_id'));
        }
        if ($request->filled('status')) {
            $q->where('tasks.status', $request->input('status'));
        }
        $sort = $request->input('sort', 'deadline');
        // Work still to do comes first; finished tasks go to the end of the list (U9).
        $q->orderByRaw("tasks.status = 'done'");
        $sort === 'new' ? $q->latest('tasks.id') : $q->orderByRaw('tasks.due_at is null')->orderBy('tasks.due_at');

        return view('team.tasks', [
            'table' => $request->input('view') === 'table',
            'tasks' => $q->paginate(20)->withQueryString(),
            'counts' => $this->ownTasks($u)->selectRaw('tasks.status, count(*) n')->groupBy('tasks.status')->pluck('n', 'status'),
            'projects' => Scope::memberProjects($u)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** My Tasks table view on desktop: server-side Yajra with the same feed as the Admin App (Technical Guide 2.1). */
    public function tasksData(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('tasks.view'), 403);
        $q = $this->ownTasks($request->user())->with(['pic:id,name', 'assignees:id,name', 'period:id,name'])->select('tasks.*')->withCount($this->subtaskCounts());

        return app(TaskListFeed::class)->send($q, $request);
    }

    /** Quick Add Task: choose a project first (the project is preset when coming from a project). */
    public function create(Request $request): View
    {
        $u = $request->user();
        $projects = Scope::memberProjects($u)->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->get(['id', 'name', 'pic_user_id', 'company_id'])
            ->filter(fn ($p) => $u->can('createTask', $p))->values();
        $selected = $projects->firstWhere('id', $request->integer('project')) ?? ($projects->count() === 1 ? $projects->first() : null);

        return view('team.task-create', [
            'projects' => $projects, 'project' => $selected, 'members' => ProjectPeople::byProject($projects->pluck('id')),
            'taskTemplates' => TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);
        if ($task->parent_id) {
            $task = Task::query()->findOrFail($task->parent_id);
        }
        // Team App: tasks in joined projects, or tasks the user holds as PIC/assignee.
        $task->loadMissing('project');
        abort_unless(Scope::isMemberOrPic($request->user(), $task->project) || Scope::isAssignee($request->user(), $task), 403);
        $task->load(['project:id,name,code,pic_user_id,company_id', 'pic:id,name', 'assignees:id,name', 'period:id,name',
            'subtasks' => fn ($s) => $s->orderBy('sort_order')->orderBy('id'),
            'submissions' => fn ($s) => $s->with(['submitter:id,name', 'reviewer:id,name', 'attachments'])->latest('id'),
            'activities' => fn ($a) => $a->with('user:id,name')->latest('id')->limit(40),
            'timeEntries' => fn ($t) => $t->with('user:id,name')->latest('work_date')->limit(20)]);
        $u = $request->user();

        // I3: ?panel=1 returns only the detail for the side panel (same data, same permissions).
        return view($request->boolean('panel') ? 'shared.task-panel-content' : 'team.task', [
            'task' => $task, 'actions' => TaskWorkflowService::primaryAction($task),
            'can' => ['update' => $u->can('update', $task), 'status' => $u->can('moveStatus', $task), 'send' => $u->can('submit', $task),
                'review' => $u->can('review', $task), 'assign' => $u->can('assign', $task), 'delete' => $u->can('delete', $task)],
            'memberOptions' => $this->members($task->project),
            'workMinutes' => (int) $task->timeEntries()->sum('minutes_spent'),
            'schedule' => $task->project->scheduleChanges()->where('task_id', $task->id)->with(['requester:id,name', 'decider:id,name'])->latest('id')->get(),
        ]);
    }

    /** My Projects: member projects only, with search. */
    public function projects(Request $request): View
    {
        abort_unless($request->user()->hasPermission('projects.view'), 403);
        $u = $request->user();
        // Technical Guide 5.2: My Projects = member projects only. The same rule is used by the detail and Kanban pages (ensureProjectMember),
        // so a project that is not in this list is also refused with 403 when opened by URL.
        $q = Scope::memberProjects($u)->with(['client:id,name', 'pic:id,name'])
            ->withCount(['tasks as main_tasks' => fn ($t) => $t->whereNull('parent_id'), 'tasks as done_tasks' => fn ($t) => $t->whereNull('parent_id')->where('status', 'done')]);
        if ($request->filled('keyword')) {
            $q->where('projects.name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('keyword')).'%');
        }

        return view('team.projects', ['projects' => $q->orderByRaw("field(projects.status,'active','on_hold','planned','completed','cancelled')")->orderBy('projects.name')->paginate(20)->withQueryString()]);
    }

    public function board(Request $request, Project $project): View
    {
        $this->ensureProjectMember($request, $project);
        $period = $request->integer('period') ?: $project->periods()->where('status', 'active')->orderByDesc('starts_at')->value('id');
        $columns = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->when($period, fn ($x) => $x->where('project_period_id', $period))
            ->with(['pic:id,name', 'assignees:id,name'])->withCount($this->subtaskCounts() + ['submissions as pending' => fn ($s) => $s->where('status', 'pending')])
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('status');

        return view('team.board', $this->header($project) + ['columns' => $columns, 'selectedPeriod' => $period,
            'taskTemplates' => TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id')]);
    }

    /** Project Summary: progress, team, notes, deadline condition. */
    public function project(Request $request, Project $project): View
    {
        $this->ensureProjectMember($request, $project);
        $project->load(['client:id,name', 'pic:id,name', 'periods']);
        $monitor = app(ManagementMetricsService::class)->monitoring($request->user())->firstWhere('id', $project->id);

        return view('team.project', $this->header($project) + [
            'progress' => ManagementMetricsService::projectProgress($project->id),
            'condition' => $monitor?->condition ?? 'on_track',
            'team' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name'),
            'notes' => $project->activities()->with('creator:id,name')->latest('id')->limit(5)->get(),
        ]);
    }

    public function calendar(Request $request): View
    {
        abort_unless($request->user()->hasPermission('calendar.view'), 403);

        return view('team.calendar');
    }

    /** My Activities: activities I logged or attended, plus extra work. */
    public function activities(Request $request): View
    {
        abort_unless($request->user()->hasPermission('activities.view'), 403);
        $u = $request->user();

        return view('team.activities', [
            'activities' => ProjectActivity::query()->whereIn('project_id', Scope::memberProjects($u)->select('projects.id'))
                ->where(fn ($w) => $w->where('created_by', $u->id)->orWhereIn('id', DB::table('project_activity_participants')->select('project_activity_id')->where('user_id', $u->id)))
                ->with(['project:id,name', 'participants:id,name', 'task:id,title'])->orderByDesc(DB::raw('coalesce(occurred_at, scheduled_at, created_at)'))->paginate(20),
            'projects' => Scope::memberProjects($u)->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->get(['id', 'name', 'pic_user_id', 'company_id'])
                ->filter(fn ($p) => $u->can('createActivity', $p))->values(),
        ]);
    }

    public function activityCreate(Request $request, Project $project): View
    {
        $this->authorize('createActivity', $project);
        $this->ensureProjectMember($request, $project);

        return view('team.activity-create', ['project' => $project, 'memberOptions' => $this->members($project),
            'taskOptions' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', '!=', 'done')->orderBy('title')->pluck('title', 'id')]);
    }

    /** Review Task (when permitted): approve or return for revision. */
    public function review(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tasks.review'), 403);

        return view('team.review', ['pending' => $this->reviewQueue($request->user())]);
    }

    private function reviewQueue($u)
    {
        return TaskSubmission::query()->where('task_submissions.status', 'pending')
            ->whereIn('task_id', Scope::tasks($u)->whereIn('tasks.project_id', Scope::memberProjects($u)->select('projects.id'))->select('tasks.id'))
            ->with(['task.project:id,name,pic_user_id,company_id', 'task.pic:id,name', 'submitter:id,name', 'attachments'])->oldest('submitted_at')->get()
            ->filter(fn ($s) => $s->task && Scope::reviewer($u, $s->task))->values();
    }

    /** My main tasks: as PIC or assignee (collaborative tasks show for every assignee). */
    private function ownTasks($u)
    {
        return Scope::tasks($u)->whereNull('tasks.parent_id')
            ->where(fn ($w) => $w->where('tasks.pic_user_id', $u->id)->orWhereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $u->id)));
    }

    /** Project detail, Kanban, and activities in the Team App: project view permission + membership (Technical Guide 5.2). */
    private function ensureProjectMember(Request $request, Project $project): void
    {
        $this->authorize('view', $project);
        abort_unless(Scope::isMemberOrPic($request->user(), $project), 403);
    }

    private function subtaskCounts(): array
    {
        return ['subtasks as sub_total', 'subtasks as sub_done' => fn ($s) => $s->where('status', 'done')];
    }

    private function members(Project $project)
    {
        return DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)
            ->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id');
    }

    private function header(Project $project): array
    {
        return ['project' => $project, 'counts' => ProjectTeamService::counts($project), 'memberOptions' => $this->members($project),
            'canCreateTask' => request()->user()->can('createTask', $project)];
    }
}
