<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Shared\TaskListFeed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Work\ProjectRequest;
use App\Models\Client;
use App\Models\Company;
use App\Models\Division;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\SystemSetting;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\ManagementMetricsService;
use App\Services\ProjectTeamService;
use App\Services\ProjectTemplateService;
use App\Services\ProjectTimelineService;
use App\Services\Scope;
use App\Services\TaskWorkflowService;
use App\Support\Codes;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ProjectController extends Controller
{
    public function __construct(private ProjectTemplateService $templates, private ProjectTeamService $teamService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        // Filter options come only from data within the user's scope (Technical Guide 3.2).
        $u = $request->user();

        return view('admin.projects.index', [
            'companies' => Scope::filterCompanies($u),
            'clients' => Scope::filterClients($u),
            'pics' => Scope::filterPics($u),
        ]);
    }

    /** Yajra feed: the Policy scope is applied BEFORE the query is handed to DataTables (Technical Guide 9.3). */
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);
        $q = Scope::projects($request->user())->with(['company:id,name', 'client:id,name', 'pic:id,name'])
            ->select('projects.*')->withCount([
                'tasks as main_tasks' => fn ($t) => $t->whereNull('parent_id'),
                'tasks as done_tasks' => fn ($t) => $t->whereNull('parent_id')->where('status', 'done'),
                'projectMembers as members' => fn ($m) => $m->where('is_active', 1),
            ]);
        foreach (['project_type', 'status', 'company_id', 'client_id', 'pic_user_id'] as $k) {
            if ($request->filled($k)) {
                $q->where('projects.'.$k, $request->input($k));
            }
        }

        return DataTables::eloquent($q)
            ->addColumn('company_name', fn (Project $p) => $p->company?->name)
            ->addColumn('client_name', fn (Project $p) => $p->client?->name ?? '-')
            ->addColumn('pic_name', fn (Project $p) => $p->pic?->name)
            ->editColumn('project_type', fn (Project $p) => Codes::label(Codes::PROJECT_TYPE, $p->project_type))
            ->addColumn('status_label', fn (Project $p) => Codes::label(Codes::PROJECT_STATUS, $p->status))
            ->addColumn('status_label_class', fn (Project $p) => $p->status)
            ->addColumn('progress', fn (Project $p) => $p->main_tasks ? (int) round($p->done_tasks * 100 / $p->main_tasks) : 0)
            ->addColumn('due', fn (Project $p) => Dates::date($p->due_at))
            ->addColumn('actions', fn (Project $p) => [['label' => 'Buka', 'url' => route('projects.show', $p)]])
            ->toJson();
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);
        // "Pakai" from the template list (U9): the template is chosen and its project type follows it.
        $template = ProjectTemplate::query()->where('is_active', 1)->find($request->integer('template'));

        return view('admin.projects.form', $this->options($request->user()) + [
            'project' => new Project(['project_type' => $template?->project_type ?? 'one_time', 'status' => 'active', 'priority' => 'normal', 'recurrence_unit' => 'month', 'recurrence_interval' => 1,
                'project_template_id' => $template?->id ?? SystemSetting::query()->where('setting_key', 'default_project_template_id')->value('setting_value')]),
            'teamDivisions' => [], 'teamMembers' => [],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);
        $t = ProjectTemplate::query()->where('is_active', 1)->find($request->integer('template'));
        $start = Dates::fromInput($request->input('start')) ?? now();

        return response()->json(['tasks' => collect($this->templates->preview($t, $start))->map(fn ($x) => $x + ['due' => Dates::date($x['due_at'])])->all(),
            'type' => $t ? Codes::label(Codes::PROJECT_TYPE, $t->project_type) : null, 'project_type' => $t?->project_type]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $d['start_at'] = Dates::fromInput($d['start_at'] ?? null) ?? now();
        $d['due_at'] = Dates::fromInput($d['due_at'] ?? null);
        $d['code'] = $d['code'] ?? null;
        $project = $this->templates->createProject($d, $request->user());

        return redirect()->route('projects.board', $project)->with('ok', "Project {$project->name} dibuat dengan ".$project->tasks()->count().' task dan subtask.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);
        $project->load(['company:id,name', 'client:id,name', 'pic:id,name', 'template:id,name', 'periods' => fn ($p) => $p->orderByDesc('starts_at')]);
        $period = request()->integer('period') ?: null;

        return view('admin.projects.show', $this->header($project) + [
            'progress' => ManagementMetricsService::projectProgress($project->id, $period),
            'selectedPeriod' => $period,
            'perStatus' => DB::table('tasks')->where('project_id', $project->id)->whereNull('parent_id')->whereNull('deleted_at')
                ->when($period, fn ($q) => $q->where('project_period_id', $period))->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'estimate' => (int) DB::table('tasks')->where('project_id', $project->id)->whereNull('parent_id')->whereNull('deleted_at')->sum('estimated_minutes'),
            'workMinutes' => (int) DB::table('task_time_entries')->join('tasks', 'tasks.id', '=', 'task_time_entries.task_id')->where('tasks.project_id', $project->id)->sum('minutes_spent'),
            'overdue' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', '!=', 'done')->where('due_at', '<', now())->orderBy('due_at')->limit(5)->get(),
            'timeline' => $project->activities()->with('creator:id,name')->latest('id')->limit(5)->get(),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        $this->authorize('update', $project);

        return view('admin.projects.form', $this->options($request->user()) + ['project' => $project,
            'teamDivisions' => $project->projectDivisions()->pluck('division_id')->all(),
            'teamMembers' => $project->projectMembers()->where('is_active', 1)->pluck('user_id')->all()]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $d = $request->validated();
        $update = collect($d)->only(['client_id', 'name', 'code', 'description', 'status', 'priority', 'pic_user_id', 'recurrence_unit', 'recurrence_interval', 'notes'])->all();
        $update['start_at'] = Dates::fromInput($d['start_at'] ?? null) ?? $project->start_at;
        if ($project->baseline_due_at === null && ! empty($d['due_at'])) {
            $update['due_at'] = $update['baseline_due_at'] = Dates::fromInput($d['due_at']);
        }
        if (($update['status'] ?? null) === 'completed' && $project->status !== 'completed') {
            $remaining = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', '!=', 'done')->count();
            if ($remaining > 0 && $project->project_type === 'one_time') {
                return back()->withInput()->with('error', "Masih ada {$remaining} task utama yang belum selesai dan diterima.");
            }
            $update['completed_at'] = now();
        }
        DB::transaction(function () use ($project, $update, $d, $request) {
            $project->update($update);
            if ($request->user()->can('manageTeam', $project)) {
                $this->teamService->setTeam($project->refresh(), $d['divisions'] ?? [], $d['members'] ?? [], $request->user());
            }
        });

        return redirect()->route('projects.show', $project)->with('ok', 'Project tersimpan.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);
        $project->delete();

        return redirect()->route('projects.index')->with('ok', "Project {$project->name} dihapus (bisa dipulihkan dari database).");
    }

    public function board(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $period = $request->integer('period') ?: $project->periods()->where('status', 'active')->orderByDesc('starts_at')->value('id');
        $q = Task::query()->where('project_id', $project->id)->whereNull('parent_id')
            ->when($period, fn ($x) => $x->where('project_period_id', $period))
            ->with(['pic:id,name', 'assignees:id,name'])
            ->withCount(['subtasks as sub_total', 'subtasks as sub_done' => fn ($s) => $s->where('status', 'done'),
                'submissions as pending' => fn ($s) => $s->where('status', 'pending')])
            ->orderBy('sort_order')->orderBy('id');
        if ($request->filled('pic')) {
            $q->where('pic_user_id', $request->integer('pic'));
        }
        if ($request->filled('members')) {
            $q->whereIn('id', DB::table('task_assignees')->select('task_id')->where('user_id', $request->integer('members')));
        }

        return view('admin.projects.board', $this->header($project) + [
            'columns' => $q->get()->groupBy('status'), 'selectedPeriod' => $period,
            'notes' => $project->activities()->where('impact_type', '!=', 'none')->where('status', '!=', 'done')->latest('id')->first(),
            'pendingSchedules' => $project->scheduleChanges()->where('status', 'pending')->count(),
            'taskTemplates' => TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function tasks(Project $project): View
    {
        $this->authorize('view', $project);

        return view('admin.projects.tasks', $this->header($project));
    }

    public function tasksData(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $q = Task::query()->where('tasks.project_id', $project->id)->whereNull('tasks.parent_id')->with(['project:id,name', 'pic:id,name', 'assignees:id,name', 'period:id,name'])
            ->select('tasks.*')->withCount(['subtasks as sub_total', 'subtasks as sub_done' => fn ($s) => $s->where('status', 'done')]);

        return app(TaskListFeed::class)->send($q, $request);
    }

    public function team(Project $project): View
    {
        $this->authorize('view', $project);
        $members = DB::table('project_members as pm')->join('users', 'users.id', '=', 'pm.user_id')->where('pm.project_id', $project->id)->where('pm.is_active', 1)
            ->select('pm.id', 'users.id as user_id', 'users.name', 'users.email')->orderBy('users.name')->get();
        $divisionsPerUser = DB::table('project_member_divisions as pmd')->join('project_divisions as pd', 'pd.id', '=', 'pmd.project_division_id')
            ->join('divisions', 'divisions.id', '=', 'pd.division_id')->whereIn('pmd.project_member_id', $members->pluck('id'))
            ->select('pmd.project_member_id', 'divisions.name')->get()->groupBy('project_member_id');
        $tasks = DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->where('tasks.project_id', $project->id)
            ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->selectRaw('task_assignees.user_id, count(*) n')->groupBy('task_assignees.user_id')->pluck('n', 'user_id');

        return view('admin.projects.team', $this->header($project) + [
            'members' => $members, 'divisionsPerUser' => $divisionsPerUser, 'tasks' => $tasks,
            'divisionNames' => DB::table('project_divisions')->join('divisions', 'divisions.id', '=', 'project_divisions.division_id')->where('project_id', $project->id)->pluck('divisions.name'),
        ] + $this->options(request()->user()) + ['teamDivisions' => $project->projectDivisions()->pluck('division_id')->all(), 'teamMembers' => $members->pluck('user_id')->all()]);
    }

    public function updateTeam(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTeam', $project);
        $d = $request->validate(['pic_user_id' => ['required', 'integer', 'exists:users,id'], 'divisions' => ['array'], 'divisions.*' => ['integer'], 'members' => ['array'], 'members.*' => ['integer']]);
        DB::transaction(function () use ($project, $d, $request) {
            $project->update(['pic_user_id' => (int) $d['pic_user_id']]);
            $this->teamService->setTeam($project->refresh(), $d['divisions'] ?? [], $d['members'] ?? [], $request->user());
        });

        return back()->with('ok', 'Tim project tersimpan.');
    }

    /** Project timeline (Technical Guide 7.3): finished tasks, activities, and deadline requests in one list + schedule condition card. */
    public function activities(Project $project, ProjectTimelineService $timeline): View
    {
        $this->authorize('view', $project);

        return view('admin.projects.activities', $this->header($project) + [
            'timeline' => $timeline->page($project, request()->has('page') ? max(1, request()->integer('page')) : null),
            'condition' => $timeline->condition($project),
            'taskOptions' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('title')->pluck('title', 'id'),
            'memberOptions' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id'),
        ]);
    }

    public function calendar(Project $project): View
    {
        $this->authorize('view', $project);

        return view('admin.projects.calendar', $this->header($project));
    }

    public function period(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $p = $this->templates->openPeriod($project, $request->user());

        return redirect()->route('projects.board', [$project, 'period' => $p->id])->with('ok', "Periode {$p->name} dibuka. Task periode lama tetap di periode lama.");
    }

    private function header(Project $project): array
    {
        $project->loadMissing(['client:id,name', 'pic:id,name', 'periods']);
        $hit = ProjectTeamService::counts($project);

        return ['project' => $project, 'counts' => $hit, 'canEdit' => request()->user()->can('update', $project),
            'canManageTeam' => request()->user()->can('manageTeam', $project), 'canCreateTask' => request()->user()->can('createTask', $project),
            'memberOptions' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)
                ->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id')];
    }

    private function options(User $user): array
    {
        $companyIds = $user->isSuperAdmin() ? null : $user->activeCompanyIds();
        $companies = Company::query()->where('is_active', 1)->when($companyIds !== null, fn ($q) => $q->whereIn('id', $companyIds ?: [0]))->orderBy('name')->get(['id', 'name']);

        return [
            'companies' => $companies->pluck('name', 'id'),
            'clients' => Client::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
            'templates' => ProjectTemplate::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'project_type']),
            'divisions' => Division::query()->where('is_active', 1)->whereIn('company_id', $companies->pluck('id'))->with('company:id,name')->orderBy('name')->get(),
            'people' => DB::table('users')->join('company_members', 'company_members.user_id', '=', 'users.id')->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')
                ->where('company_members.is_active', 1)->where('user_profiles.is_active', 1)->whereIn('company_members.company_id', $companies->pluck('id'))
                ->select('users.id', 'users.name', 'company_members.company_id')->orderBy('users.name')->get(),
        ];
    }
}
