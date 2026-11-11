<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Services\Scope;
use App\Services\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Halaman task Admin: Semua Task, Task Saya, Menunggu Review, Detail Task. Aksi task ada di Bersama\TaskAksiController. */
class TaskController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Task::class);

        return view('admin.tasks.index', ['judul' => 'Semua Task', 'feed' => route('tasks.data'), 'saya' => false,
            'orang' => User::query()->whereIn('id', DB::table('task_assignees')->select('user_id'))->orderBy('name')->pluck('name', 'id')]);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        return app(TaskListFeed::class)->kirim($this->dasar($request->user()), $request);
    }

    public function mine(): View
    {
        $this->authorize('viewAny', Task::class);

        return view('admin.tasks.index', ['judul' => 'Task Saya', 'feed' => route('tasks.mine.data'), 'saya' => true, 'orang' => collect()]);
    }

    /** Task pribadi termasuk task kolaboratif (sebagai PIC atau salah satu assignee). */
    public function mineData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);
        $u = $request->user();
        $q = $this->dasar($u)->where(fn ($w) => $w->where('tasks.pic_user_id', $u->id)->orWhereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $u->id)));

        return app(TaskListFeed::class)->kirim($q, $request);
    }

    public function review(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tasks.review'), 403);
        $u = $request->user();
        $menunggu = TaskSubmission::query()->where('task_submissions.status', 'pending')
            ->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))
            ->with(['task.project:id,name,pic_user_id,company_id', 'task.pic:id,name', 'submitter:id,name', 'attachments'])->oldest('submitted_at')->get()
            ->filter(fn ($s) => $s->task && Scope::reviewer($u, $s->task));
        $riwayat = TaskSubmission::query()->whereIn('task_submissions.status', ['approved', 'revision'])
            ->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))->with(['task:id,title,project_id', 'submitter:id,name', 'reviewer:id,name'])
            ->latest('reviewed_at')->limit(20)->get();

        return view('admin.tasks.review', ['menunggu' => $menunggu, 'riwayat' => $riwayat]);
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

        return view('admin.tasks.show', [
            'task' => $task,
            'aksi' => TaskWorkflowService::aksiUtama($task),
            'boleh' => ['ubah' => $u->can('update', $task), 'status' => $u->can('moveStatus', $task), 'kirim' => $u->can('submit', $task),
                'review' => $u->can('review', $task), 'assign' => $u->can('assign', $task), 'hapus' => $u->can('delete', $task)],
            'anggotaPilihan' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $task->project_id)
                ->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id'),
            'menitKerja' => (int) $task->timeEntries()->sum('minutes_spent'),
            'jadwal' => $task->project->scheduleChanges()->where('task_id', $task->id)->with(['requester:id,name', 'decider:id,name'])->latest('id')->get(),
        ]);
    }

    private function dasar(User $u)
    {
        return Scope::tasks($u)->whereNull('tasks.parent_id')->with(['project:id,name', 'pic:id,name', 'assignees:id,name', 'period:id,name'])
            ->select('tasks.*')->withCount(['subtasks as sub_total', 'subtasks as sub_selesai' => fn ($s) => $s->where('status', 'done')]);
    }
}
