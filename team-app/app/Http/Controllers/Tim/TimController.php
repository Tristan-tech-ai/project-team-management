<?php

namespace App\Http\Controllers\Tim;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\TaskTemplate;
use App\Services\ManagementMetricsService;
use App\Services\ProjectTeamService;
use App\Services\Scope;
use App\Services\TaskWorkflowService;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Halaman aplikasi Tim (Petunjuk Teknis 5.2). Semua aksi memakai endpoint bersama (Bersama\TaskAksiController,
 * Bersama\OperasionalController) sehingga aturan status, izin, dan deadline sama persis dengan Admin.
 */
class TimController extends Controller
{
    /** Ringkasan Saya: tugas hari ini, terlambat, review, quick add. */
    public function beranda(Request $request): View
    {
        $u = $request->user();
        $milik = $this->milik($u);
        $zona = Waktu::zona();
        $hariIni = [Carbon::now($zona)->startOfDay()->utc(), Carbon::now($zona)->endOfDay()->utc()];

        return view('tim.beranda', [
            'hitung' => (clone $milik)->selectRaw('tasks.status, count(*) n')->groupBy('tasks.status')->pluck('n', 'status'),
            'hariIni' => (clone $milik)->where('tasks.status', '!=', 'done')->whereBetween('tasks.due_at', $hariIni)->with(['project:id,name'])->withCount($this->hitungSub())->orderBy('tasks.due_at')->get(),
            'terlambat' => (clone $milik)->where('tasks.status', '!=', 'done')->where('tasks.due_at', '<', now())->with(['project:id,name'])->withCount($this->hitungSub())->orderBy('tasks.due_at')->limit(5)->get(),
            'diReview' => (clone $milik)->where('tasks.status', 'review')->with(['project:id,name'])->withCount($this->hitungSub())->limit(5)->get(),
            'revisi' => TaskSubmission::query()->where('submitted_by', $u->id)->where('status', 'revision')->where('reviewed_at', '>=', now()->subDays(7))
                ->with('task:id,title,status')->latest('reviewed_at')->limit(3)->get()->filter(fn ($s) => $s->task?->status === 'in_progress'),
            'menungguSaya' => $u->hasPermission('tasks.review') ? $this->antrianReview($u)->count() : 0,
            'projects' => Scope::projects($u)->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->limit(4)->get(['id', 'name', 'project_type']),
        ]);
    }

    /** Task Saya: kartu ringkas, filter project, urut deadline (prototype 7.4). */
    public function tasks(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tasks.view'), 403);
        $u = $request->user();
        $q = $this->milik($u)->with(['project:id,name', 'assignees:id,name', 'pic:id,name'])->withCount($this->hitungSub());
        if ($request->filled('project_id')) {
            $q->where('tasks.project_id', $request->integer('project_id'));
        }
        if ($request->filled('status')) {
            $q->where('tasks.status', $request->input('status'));
        }
        $urut = $request->input('urut', 'deadline');
        $urut === 'baru' ? $q->latest('tasks.id') : $q->orderByRaw('tasks.due_at is null')->orderBy('tasks.due_at');

        return view('tim.tasks', [
            'tasks' => $q->paginate(20)->withQueryString(),
            'hitung' => $this->milik($u)->selectRaw('tasks.status, count(*) n')->groupBy('tasks.status')->pluck('n', 'status'),
            'projects' => Scope::projects($u)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Quick Add Task: pilih project dulu (project terisi otomatis bila datang dari project). */
    public function create(Request $request): View
    {
        $u = $request->user();
        $projects = Scope::projects($u)->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->get(['id', 'name', 'pic_user_id', 'company_id'])
            ->filter(fn ($p) => $u->can('createTask', $p))->values();
        $dipilih = $projects->firstWhere('id', $request->integer('project')) ?? ($projects->count() === 1 ? $projects->first() : null);

        return view('tim.task-baru', [
            'projects' => $projects, 'project' => $dipilih,
            'anggotaPilihan' => $dipilih ? $this->anggota($dipilih) : collect(),
            'templatesTask' => TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);
        if ($task->parent_id) {
            $task = Task::query()->findOrFail($task->parent_id);
        }
        $task->load(['project:id,name,code,pic_user_id,company_id', 'pic:id,name', 'assignees:id,name', 'period:id,name',
            'subtasks' => fn ($s) => $s->orderBy('sort_order')->orderBy('id'),
            'submissions' => fn ($s) => $s->with(['submitter:id,name', 'reviewer:id,name', 'attachments'])->latest('id'),
            'activities' => fn ($a) => $a->with('user:id,name')->latest('id')->limit(40),
            'timeEntries' => fn ($t) => $t->with('user:id,name')->latest('work_date')->limit(20)]);
        $u = $request->user();

        return view('tim.task', [
            'task' => $task, 'aksi' => TaskWorkflowService::aksiUtama($task),
            'boleh' => ['ubah' => $u->can('update', $task), 'status' => $u->can('moveStatus', $task), 'kirim' => $u->can('submit', $task),
                'review' => $u->can('review', $task), 'assign' => $u->can('assign', $task), 'hapus' => $u->can('delete', $task)],
            'anggotaPilihan' => $this->anggota($task->project),
            'menitKerja' => (int) $task->timeEntries()->sum('minutes_spent'),
            'jadwal' => $task->project->scheduleChanges()->where('task_id', $task->id)->with(['requester:id,name', 'decider:id,name'])->latest('id')->get(),
        ]);
    }

    /** Project Saya: hanya project keanggotaan, dengan cari. */
    public function projects(Request $request): View
    {
        abort_unless($request->user()->hasPermission('projects.view'), 403);
        $u = $request->user();
        // Satu aturan cakupan untuk daftar, detail, dan Kanban (Scope::projects = ProjectPolicy::view), juga sama dengan Admin App:
        // project yang tidak ada di daftar ini juga ditolak 403 bila dibuka lewat URL.
        $q = Scope::projects($u)->with(['client:id,name', 'pic:id,name'])
            ->withCount(['tasks as task_utama' => fn ($t) => $t->whereNull('parent_id'), 'tasks as task_selesai' => fn ($t) => $t->whereNull('parent_id')->where('status', 'done')]);
        if ($request->filled('cari')) {
            $q->where('projects.name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('cari')).'%');
        }

        return view('tim.projects', ['projects' => $q->orderByRaw("field(projects.status,'active','on_hold','planned','completed','cancelled')")->orderBy('projects.name')->paginate(20)->withQueryString()]);
    }

    public function board(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $periode = $request->integer('periode') ?: $project->periods()->where('status', 'active')->orderByDesc('starts_at')->value('id');
        $kolom = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->when($periode, fn ($x) => $x->where('project_period_id', $periode))
            ->with(['pic:id,name', 'assignees:id,name'])->withCount($this->hitungSub() + ['submissions as menunggu' => fn ($s) => $s->where('status', 'pending')])
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('status');

        return view('tim.board', $this->kepala($project) + ['kolom' => $kolom, 'periodeDipilih' => $periode,
            'templatesTask' => TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id')]);
    }

    /** Ringkasan Project: progress, tim, catatan, kondisi deadline. */
    public function project(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $project->load(['client:id,name', 'pic:id,name', 'periods']);
        $monitor = app(ManagementMetricsService::class)->monitoring($request->user())->firstWhere('id', $project->id);

        return view('tim.project', $this->kepala($project) + [
            'progres' => ManagementMetricsService::progresProject($project->id),
            'kondisi' => $monitor?->kondisi ?? 'on_track',
            'tim' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name'),
            'catatan' => $project->activities()->with('creator:id,name')->latest('id')->limit(5)->get(),
        ]);
    }

    public function calendar(Request $request): View
    {
        abort_unless($request->user()->hasPermission('calendar.view'), 403);

        return view('tim.kalender');
    }

    /** Aktivitas Saya: kegiatan yang saya catat atau saya hadiri, plus pekerjaan tambahan. */
    public function activities(Request $request): View
    {
        abort_unless($request->user()->hasPermission('activities.view'), 403);
        $u = $request->user();

        return view('tim.aktivitas', [
            'aktivitas' => ProjectActivity::query()->whereIn('project_id', Scope::projects($u)->select('projects.id'))
                ->where(fn ($w) => $w->where('created_by', $u->id)->orWhereIn('id', DB::table('project_activity_participants')->select('project_activity_id')->where('user_id', $u->id)))
                ->with(['project:id,name', 'participants:id,name', 'task:id,title'])->orderByDesc(DB::raw('coalesce(occurred_at, scheduled_at, created_at)'))->paginate(20),
            'projects' => Scope::projects($u)->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->get(['id', 'name', 'pic_user_id', 'company_id'])
                ->filter(fn ($p) => $u->can('createActivity', $p))->values(),
        ]);
    }

    public function activityCreate(Request $request, Project $project): View
    {
        $this->authorize('createActivity', $project);

        return view('tim.aktivitas-baru', ['project' => $project, 'anggotaPilihan' => $this->anggota($project),
            'tasksPilihan' => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', '!=', 'done')->orderBy('title')->pluck('title', 'id')]);
    }

    /** Review Task (jika berizin): terima atau kembalikan untuk revisi. */
    public function review(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tasks.review'), 403);

        return view('tim.review', ['menunggu' => $this->antrianReview($request->user())]);
    }

    private function antrianReview($u)
    {
        return TaskSubmission::query()->where('task_submissions.status', 'pending')->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))
            ->with(['task.project:id,name,pic_user_id,company_id', 'task.pic:id,name', 'submitter:id,name', 'attachments'])->oldest('submitted_at')->get()
            ->filter(fn ($s) => $s->task && Scope::reviewer($u, $s->task))->values();
    }

    /** Task utama milik saya: sebagai PIC atau assignee (task kolaboratif tampil untuk semua pelaksana). */
    private function milik($u)
    {
        return Scope::tasks($u)->whereNull('tasks.parent_id')
            ->where(fn ($w) => $w->where('tasks.pic_user_id', $u->id)->orWhereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $u->id)));
    }

    private function hitungSub(): array
    {
        return ['subtasks as sub_total', 'subtasks as sub_selesai' => fn ($s) => $s->where('status', 'done')];
    }

    private function anggota(Project $project)
    {
        return DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $project->id)
            ->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id');
    }

    private function kepala(Project $project): array
    {
        return ['project' => $project, 'hitung' => ProjectTeamService::hitung($project), 'anggotaPilihan' => $this->anggota($project),
            'bolehTask' => request()->user()->can('createTask', $project)];
    }
}
