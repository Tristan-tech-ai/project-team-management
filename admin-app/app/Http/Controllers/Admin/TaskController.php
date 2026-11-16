<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Shared\TaskListFeed;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\TaskTemplate;
use App\Support\ProjectPeople;
use App\Models\User;
use App\Http\Requests\Work\BulkTaskRequest;
use App\Services\Scope;
use App\Services\TaskBulkService;
use App\Services\TaskWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Admin task pages: All Tasks, My Tasks, Waiting for Review, Task Detail. Task actions live in Shared\TaskActionController. */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);

        return view('admin.tasks.index', ['title' => 'Semua Task', 'feed' => route('tasks.data'), 'mine' => false,
            'people' => Scope::filterAssignees($request->user()),
            'bulk' => TaskBulkService::options($request->user(), Scope::projects($request->user())->select('projects.id'))]);
    }

    /** I13: POST /tasks/bulk starts several tasks, changes their PIC, or adds an assignee; skipped tasks come back with the reason. */
    public function bulk(BulkTaskRequest $request, TaskBulkService $bulk): JsonResponse
    {
        $result = $bulk->apply($request->user(), (array) $request->input('ids'), $request->input('action'), $request->integer('user_id') ?: null);
        $message = $result['done'] ? "{$result['done']} task diperbarui." : 'Tidak ada task yang berubah.';
        if ($result['skipped']) {
            $message .= ' '.count($result['skipped']).' dilewati.';
        }

        return response()->json(['message' => $message] + $result);
    }

    public function data(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        return app(TaskListFeed::class)->send($this->baseQuery($request->user()), $request);
    }

    /** Quick Add from the Tasks page (Technical Guide 6.3): choose a project first, then title, PIC, assignees, and deadline. */
    public function create(Request $request): View
    {
        $u = $request->user();
        $projects = Scope::projects($u)->whereIn('status', ['planned', 'active', 'on_hold'])->orderBy('name')->get(['id', 'name', 'pic_user_id', 'company_id'])
            ->filter(fn ($p) => $u->can('createTask', $p))->values();
        $selected = $projects->firstWhere('id', $request->integer('project')) ?? ($projects->count() === 1 ? $projects->first() : null);

        return view('admin.tasks.create', [
            'projects' => $projects, 'project' => $selected, 'members' => ProjectPeople::byProject($projects->pluck('id')),
            'taskTemplates' => TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function mine(): View
    {
        $this->authorize('viewAny', Task::class);

        return view('admin.tasks.index', ['title' => 'Task Saya', 'feed' => route('tasks.mine.data'), 'mine' => true, 'people' => collect()]);
    }

    /** Personal tasks including collaborative ones (as PIC or one of the assignees). */
    public function mineData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);
        $u = $request->user();
        $q = $this->baseQuery($u)->where(fn ($w) => $w->where('tasks.pic_user_id', $u->id)->orWhereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $u->id)));

        return app(TaskListFeed::class)->send($q, $request);
    }

    public function review(Request $request): View
    {
        abort_unless($request->user()->hasPermission('tasks.review'), 403);
        $u = $request->user();
        $pending = TaskSubmission::query()->where('task_submissions.status', 'pending')
            ->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))
            ->with(['task.project:id,name,pic_user_id,company_id', 'task.pic:id,name', 'submitter:id,name', 'attachments'])->oldest('submitted_at')->get()
            ->filter(fn ($s) => $s->task && Scope::reviewer($u, $s->task));
        $history = TaskSubmission::query()->whereIn('task_submissions.status', ['approved', 'revision'])
            ->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))->with(['task:id,title,project_id', 'submitter:id,name', 'reviewer:id,name'])
            ->latest('reviewed_at')->limit(20)->get();

        return view('admin.tasks.review', ['pending' => $pending, 'history' => $history]);
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

        // I3: ?panel=1 returns only the detail for the side panel (same data, same permissions).
        return view($request->boolean('panel') ? 'shared.task-panel-content' : 'admin.tasks.show', [
            'task' => $task,
            'actions' => TaskWorkflowService::primaryAction($task),
            'can' => ['update' => $u->can('update', $task), 'status' => $u->can('moveStatus', $task), 'send' => $u->can('submit', $task),
                'review' => $u->can('review', $task), 'assign' => $u->can('assign', $task), 'delete' => $u->can('delete', $task)],
            'memberOptions' => DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_id', $task->project_id)
                ->where('project_members.is_active', 1)->orderBy('users.name')->pluck('users.name', 'users.id'),
            'workMinutes' => (int) $task->timeEntries()->sum('minutes_spent'),
            'schedule' => $task->project->scheduleChanges()->where('task_id', $task->id)->with(['requester:id,name', 'decider:id,name'])->latest('id')->get(),
        ]);
    }

    private function baseQuery(User $u)
    {
        return Scope::tasks($u)->whereNull('tasks.parent_id')->with(['project:id,name', 'pic:id,name', 'assignees:id,name', 'period:id,name'])
            ->select('tasks.*')->withCount(['subtasks as sub_total', 'subtasks as sub_done' => fn ($s) => $s->where('status', 'done')]);
    }
}
