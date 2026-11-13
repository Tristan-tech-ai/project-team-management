<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kerja\ProjectRequest;
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
use App\Services\Scope;
use App\Services\TaskWorkflowService;
use App\Support\Kode;
use App\Support\Waktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class ProjectController extends Controller
{
    public function __construct(private ProjectTemplateService $templat, private ProjectTeamService $tim) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        // Pilihan filter hanya dari data dalam cakupan pengguna (Petunjuk Teknis 3.2).
        $u = $request->user();

        return view('admin.projects.index', [
            'companies' => Scope::filterCompanies($u),
            'clients' => Scope::filterClients($u),
            'pics' => Scope::filterPics($u),
        ]);
    }

    /** Feed Yajra: scope Policy diterapkan SEBELUM query diserahkan ke DataTables (Petunjuk Teknis 9.3). */
    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);
        $q = Scope::projects($request->user())->with(['company:id,name', 'client:id,name', 'pic:id,name'])
            ->select('projects.*')->withCount([
                'tasks as task_utama' => fn ($t) => $t->whereNull('parent_id'),
                'tasks as task_selesai' => fn ($t) => $t->whereNull('parent_id')->where('status', 'done'),
                'projectMembers as anggota' => fn ($m) => $m->where('is_active', 1),
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
            ->editColumn('project_type', fn (Project $p) => Kode::label(Kode::PROJECT_TYPE, $p->project_type))
            ->addColumn('status_label', fn (Project $p) => Kode::label(Kode::PROJECT_STATUS, $p->status))
            ->addColumn('status_label_kelas', fn (Project $p) => $p->status)
            ->addColumn('progres', fn (Project $p) => $p->task_utama ? (int) round($p->task_selesai * 100 / $p->task_utama) : 0)
            ->addColumn('due', fn (Project $p) => Waktu::tanggal($p->due_at))
            ->addColumn('aksi', fn (Project $p) => [['label' => 'Buka', 'url' => route('projects.show', $p)]])
            ->toJson();
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Project::class);

        return view('admin.projects.form', $this->pilihan($request->user()) + [
            'project' => new Project(['project_type' => 'one_time', 'status' => 'active', 'priority' => 'normal', 'recurrence_unit' => 'month', 'recurrence_interval' => 1,
                'project_template_id' => SystemSetting::query()->where('setting_key', 'default_project_template_id')->value('setting_value')]),
            'timDivisi' => [], 'timAnggota' => [],
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('create', Project::class);
        $t = ProjectTemplate::query()->where('is_active', 1)->find($request->integer('template'));
        $mulai = Waktu::dariInput($request->input('mulai')) ?? now();

        return response()->json(['tasks' => collect($this->templat->pratinjau($t, $mulai))->map(fn ($x) => $x + ['due' => Waktu::tanggal($x['due_at'])])->all(),
            'tipe' => $t ? Kode::label(Kode::PROJECT_TYPE, $t->project_type) : null]);
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $d = $request->validated();
        $d['start_at'] = Waktu::dariInput($d['start_at'] ?? null) ?? now();
        $d['due_at'] = Waktu::dariInput($d['due_at'] ?? null);
        $d['code'] = $d['code'] ?? null;
        $project = $this->templat->buatProject($d, $request->user());

        return redirect()->route('projects.board', $project)->with('ok', "Project {$project->name} dibuat dengan ".$project->tasks()->count().' task dan subtask.');
    }

    public function show(Project $project): View
    {
        $this->authorize('view', $project);
        $project->load(['company:id,name', 'client:id,name', 'pic:id,name', 'template:id,name', 'periods' => fn ($p) => $p->orderByDesc('starts_at')]);
        $periode = request()->integer('periode') ?: null;

        return view('admin.projects.show', $this->kepala($project) + [
            'progres' => ManagementMetricsService::progresProject($project->id, $periode),
            'periodeDipilih' => $periode,
            'perStatus' => DB::table('tasks')->where('project_id', $project->id)->whereNull('parent_id')->whereNull('deleted_at')
                ->when($periode, fn ($q) => $q->where('project_period_id', $periode))->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'estimasi' => (int) DB::table('tasks')->where('project_id', $project->id)->whereNull('parent_id')->whereNull('deleted_at')->sum('estimated_minutes'),
            'menitKerja' => (int) DB::table('task_time_entries')->join('tasks', 'tasks.id', '=', 'task_time_entries.task_id')->where('tasks.project_id', $project->id)->sum('minutes_spent'),
            'terlambat' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', '!=', 'done')->where('due_at', '<', now())->orderBy('due_at')->limit(5)->get(),
            'timeline' => $project->activities()->with('creator:id,name')->latest('id')->limit(5)->get(),
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        $this->authorize('update', $project);

        return view('admin.projects.form', $this->pilihan($request->user()) + ['project' => $project,
            'timDivisi' => $project->projectDivisions()->pluck('division_id')->all(),
            'timAnggota' => $project->projectMembers()->where('is_active', 1)->pluck('user_id')->all()]);
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $d = $request->validated();
        $ubah = collect($d)->only(['client_id', 'name', 'code', 'description', 'status', 'priority', 'pic_user_id', 'recurrence_unit', 'recurrence_interval', 'notes'])->all();
        $ubah['start_at'] = Waktu::dariInput($d['start_at'] ?? null) ?? $project->start_at;
        if ($project->baseline_due_at === null && ! empty($d['due_at'])) {
            $ubah['due_at'] = $ubah['baseline_due_at'] = Waktu::dariInput($d['due_at']);
        }
        if (($ubah['status'] ?? null) === 'completed' && $project->status !== 'completed') {
            $belum = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', '!=', 'done')->count();
            if ($belum > 0 && $project->project_type === 'one_time') {
                return back()->withInput()->with('galat', "Masih ada {$belum} task utama yang belum selesai dan diterima.");
            }
            $ubah['completed_at'] = now();
        }
        DB::transaction(function () use ($project, $ubah, $d, $request) {
            $project->update($ubah);
            if ($request->user()->can('manageTeam', $project)) {
                $this->tim->aturTim($project->refresh(), $d['divisions'] ?? [], $d['members'] ?? [], $request->user());
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
        $periode = $request->integer('periode') ?: $project->periods()->where('status', 'active')->orderByDesc('starts_at')->value('id');
        $q = Task::query()->where('project_id', $project->id)->whereNull('parent_id')
            ->when($periode, fn ($x) => $x->where('project_period_id', $periode))
            ->with(['pic:id,name', 'assignees:id,name'])
            ->withCount(['subtasks as sub_total', 'subtasks as sub_selesai' => fn ($s) => $s->where('status', 'done'),
                'submissions as menunggu' => fn ($s) => $s->where('status', 'pending')])
            ->orderBy('sort_order')->orderBy('id');
        if ($request->filled('pic')) {
            $q->where('pic_user_id', $request->integer('pic'));
        }
        if ($request->filled('anggota')) {
            $q->whereIn('id', DB::table('task_assignees')->select('task_id')->where('user_id', $request->integer('anggota')));
        }

        return view('admin.projects.board', $this->kepala($project) + [
            'kolom' => $q->get()->groupBy('status'), 'periodeDipilih' => $periode,
            'catatan' => $project->activities()->where('impact_type', '!=', 'none')->where('status', '!=', 'done')->latest('id')->first(),
            'jadwalMenunggu' => $project->scheduleChanges()->where('status', 'pending')->count(),
            'templatesTask' => TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function tasks(Project $project): View
    {
        $this->authorize('view', $project);

        return view('admin.projects.tasks', $this->kepala($project));
    }

    public function tasksData(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        $q = Task::query()->where('tasks.project_id', $project->id)->whereNull('tasks.parent_id')->with(['project:id,name', 'pic:id,name', 'assignees:id,name', 'period:id,name'])
            ->select('tasks.*')->withCount(['subtasks as sub_total', 'subtasks as sub_selesai' => fn ($s) => $s->where('status', 'done')]);

        return app(TaskListFeed::class)->kirim($q, $request);
    }

    public function team(Project $project): View
    {
        $this->authorize('view', $project);
        $anggota = DB::table('project_members as pm')->join('users', 'users.id', '=', 'pm.user_id')->where('pm.project_id', $project->id)->where('pm.is_active', 1)
            ->select('pm.id', 'users.id as user_id', 'users.name', 'users.email')->orderBy('users.name')->get();
        $divisiPer = DB::table('project_member_divisions as pmd')->join('project_divisions as pd', 'pd.id', '=', 'pmd.project_division_id')
            ->join('divisions', 'divisions.id', '=', 'pd.division_id')->whereIn('pmd.project_member_id', $anggota->pluck('id'))
            ->select('pmd.project_member_id', 'divisions.name')->get()->groupBy('project_member_id');
        $tugas = DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->where('tasks.project_id', $project->id)
            ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->selectRaw('task_assignees.user_id, count(*) n')->groupBy('task_assignees.user_id')->pluck('n', 'user_id');

        return view('admin.projects.team', $this->kepala($project) + [
            'anggota' => $anggota, 'divisiPer' => $divisiPer, 'tugas' => $tugas,
            'divisi' => DB::table('project_divisions')->join('divisions', 'divisions.id', '=', 'project_divisions.division_id')->where('project_id', $project->id)->pluck('divisions.name'),
        ] + $this->pilihan(request()->user()) + ['timDivisi' => $project->projectDivisions()->pluck('division_id')->all(), 'timAnggota' => $anggota->pluck('user_id')->all()]);
    }

    public function updateTeam(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageTeam', $project);
        $d = $request->validate(['pic_user_id' => ['required', 'integer', 'exists:users,id'], 'divisions' => ['array'], 'divisions.*' => ['integer'], 'members' => ['array'], 'members.*' => ['integer']]);
        DB::transaction(function () use ($project, $d, $request) {
            $project->update(['pic_user_id' => (int) $d['pic_user_id']]);
            $this->tim->aturTim($project->refresh(), $d['divisions'] ?? [], $d['members'] ?? [], $request->user());
        });

        return back()->with('ok', 'Tim project tersimpan.');
    }

    public function activities(Project $project): View
    {
        $this->authorize('view', $project);

        return view('admin.projects.activities', $this->kepala($project) + [
            'aktivitas' => $project->activities()->with(['creator:id,name', 'participants:id,name', 'task:id,title', 'scheduleChanges'])->orderByDesc(DB::raw('coalesce(occurred_at, scheduled_at, created_at)'))->paginate(20),
            'jadwal' => $project->scheduleChanges()->with(['task:id,title,baseline_due_at', 'requester:id,name', 'decider:id,name', 'activity:id,title'])->latest('id')->get(),
            'tasksPilihan' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('title')->pluck('title', 'id'),
            'anggotaPilihan' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id'),
        ]);
    }

    public function calendar(Project $project): View
    {
        $this->authorize('view', $project);

        return view('admin.projects.calendar', $this->kepala($project));
    }

    public function periode(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);
        $p = $this->templat->bukaPeriode($project, $request->user());

        return redirect()->route('projects.board', [$project, 'periode' => $p->id])->with('ok', "Periode {$p->name} dibuka. Task periode lama tetap di periode lama.");
    }

    private function kepala(Project $project): array
    {
        $project->loadMissing(['client:id,name', 'pic:id,name', 'periods']);
        $hit = ProjectTeamService::hitung($project);

        return ['project' => $project, 'hitung' => $hit, 'bolehUbah' => request()->user()->can('update', $project),
            'bolehTim' => request()->user()->can('manageTeam', $project), 'bolehTask' => request()->user()->can('createTask', $project),
            'anggotaPilihan' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)
                ->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id')];
    }

    private function pilihan(User $user): array
    {
        $companyIds = $user->isSuperAdmin() ? null : $user->activeCompanyIds();
        $companies = Company::query()->where('is_active', 1)->when($companyIds !== null, fn ($q) => $q->whereIn('id', $companyIds ?: [0]))->orderBy('name')->get(['id', 'name']);

        return [
            'companies' => $companies->pluck('name', 'id'),
            'clients' => Client::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
            'templates' => ProjectTemplate::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'project_type']),
            'divisions' => Division::query()->where('is_active', 1)->whereIn('company_id', $companies->pluck('id'))->with('company:id,name')->orderBy('name')->get(),
            'orang' => DB::table('users')->join('company_members', 'company_members.user_id', '=', 'users.id')->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')
                ->where('company_members.is_active', 1)->where('user_profiles.is_active', 1)->whereIn('company_members.company_id', $companies->pluck('id'))
                ->select('users.id', 'users.name', 'company_members.company_id')->orderBy('users.name')->get(),
        ];
    }
}
