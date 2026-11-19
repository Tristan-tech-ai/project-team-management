<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Work\AssigneeRequest;
use App\Http\Requests\Work\QuickTaskRequest;
use App\Http\Requests\Work\ReviewRequest;
use App\Http\Requests\Work\StatusRequest;
use App\Http\Requests\Work\SubmissionRequest;
use App\Http\Requests\Work\SubtaskRequest;
use App\Http\Requests\Work\TaskUpdateRequest;
use App\Http\Requests\Work\TimeEntryRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskSubmission;
use App\Models\TaskTemplate;
use App\Models\TaskTimeEntry;
use App\Services\ProjectTeamService;
use App\Services\ProjectTemplateService;
use App\Services\TaskWorkflowService;
use App\Support\Files;
use App\Support\Codes;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Task endpoints that are exactly the same in admin-app and team-app (Technical Guide 9.2):
 * POST /projects/{id}/tasks, PATCH /tasks/{id}/status, PATCH /subtasks/{id}/toggle, POST /tasks/{id}/submissions,
 * POST /submissions/{id}/review, plus task edits, assignees, subtasks, and time entries.
 */
class TaskActionController extends Controller
{
    public function __construct(private TaskWorkflowService $workflow, private ProjectTeamService $teamService) {}

    public function store(QuickTaskRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $d = $request->validated();
        $assignees = array_values(array_unique(array_map('intval', array_merge($d['assignees'] ?? [], [(int) $d['pic_user_id']]))));
        $this->teamService->ensureProjectMembers($project, $assignees);
        $period = $d['project_period_id'] ?? $project->periods()->where('status', 'active')->orderByDesc('starts_at')->value('id');
        $due = Dates::fromInput($d['due_at'] ?? null);
        $attributes = ['project_id' => $project->id, 'project_period_id' => $period, 'parent_id' => null, 'pic_user_id' => (int) $d['pic_user_id'],
            'created_by' => $request->user()->id, 'title' => $d['title'], 'description' => $d['description'] ?? null,
            'task_type' => $d['task_type'] ?? 'general', 'status' => 'backlog', 'priority' => $d['priority'] ?? 'normal',
            'baseline_due_at' => $due, 'due_at' => $due,
            'sort_order' => (int) Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', 'backlog')->max('sort_order') + 1];
        $task = DB::transaction(function () use ($d, $attributes, $project, $assignees, $request) {
            $task = ! empty($d['task_template_id'])
                ? app(ProjectTemplateService::class)->applyTaskTemplate($project, TaskTemplate::query()->findOrFail($d['task_template_id']), $attributes, $request->user())
                : Task::create($attributes);
            foreach ($assignees as $uid) {
                $this->teamService->ensureAssignee($task, $uid, $request->user());
            }
            $this->workflow->log($task, $request->user(), 'created', 'Task dibuat');

            return $task;
        });

        return $this->respond($request, "Task {$task->title} ditambahkan ke Antrian.", ['task_id' => $task->id]);
    }

    public function update(TaskUpdateRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $d = $request->validated();
        foreach (['scheduled_publish_at', 'published_at'] as $k) {
            $d[$k] = Dates::fromInput($d[$k] ?? null);
        }
        if (array_key_exists('due_at', $d)) {
            $d['due_at'] = Dates::fromInput($d['due_at']);
            $d['baseline_due_at'] = $d['due_at'];
        }
        $task->update($d);
        $this->workflow->log($task, $request->user(), 'updated', 'Isi task diubah');

        return $this->respond($request, 'Task tersimpan.');
    }

    public function status(StatusRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $t = $this->workflow->move($task, $request->input('status'), $request->user(), $request->input('notes'));

        return $this->respond($request, 'Status: '.Codes::label(Codes::TASK_STATUS, $t->status).'.', ['status' => $t->status, 'label' => Codes::label(Codes::TASK_STATUS, $t->status)]);
    }

    public function assignees(AssigneeRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->teamService->setAssignees($task, (int) $request->input('pic_user_id'), (array) $request->input('assignees', []), $request->user());

        return $this->respond($request, 'PIC dan pelaksana tersimpan.');
    }

    public function subtask(SubtaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        if ($task->parent_id !== null) {
            return $this->respond($request, 'Subtask hanya satu tingkat: tambahkan ke task utamanya.', [], 422);
        }
        $s = Task::create(['project_id' => $task->project_id, 'project_period_id' => $task->project_period_id, 'parent_id' => $task->id,
            'pic_user_id' => $task->pic_user_id, 'created_by' => $request->user()->id, 'title' => $request->input('title'), 'status' => 'backlog',
            'sort_order' => (int) $task->subtasks()->max('sort_order') + 1]);
        $this->workflow->log($task, $request->user(), 'subtask_added', 'Checklist ditambah: '.$s->title);

        return $this->respond($request, 'Checklist ditambah.', ['subtask_id' => $s->id]);
    }

    public function toggle(Request $request, Task $subtask): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $subtask);
        $before = Task::query()->whereKey($subtask->parent_id)->value('status');
        $s = $this->workflow->toggleSubtask($subtask, $request->user(), $request->boolean('done', $subtask->status !== 'done'));
        $parent = $s->parent_id;
        $total = Task::query()->where('parent_id', $parent)->count();
        $done = Task::query()->where('parent_id', $parent)->where('status', 'done')->count();
        $started = $before === 'backlog' && Task::query()->whereKey($parent)->value('status') === 'in_progress';

        return $this->respond($request, $s->status === 'done' ? 'Checklist selesai.'.($started ? ' Task kini Dikerjakan.' : '') : 'Checklist dibuka lagi.',
            ['done' => $s->status === 'done', 'progress' => TaskWorkflowService::taskProgress($done, $total), 'total' => "{$done} / {$total}"]);
    }

    public function submit(SubmissionRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->workflow->submitResult($task, $request->user(), $request->input('external_url'), $request->file('file'), $request->input('submission_note'));

        return $this->respond($request, 'Hasil terkirim. Task masuk Review.');
    }

    public function review(ReviewRequest $request, TaskSubmission $submission): JsonResponse|RedirectResponse
    {
        $t = $this->workflow->decide($submission, $request->input('decision'), $request->user(), $request->input('review_note'));

        return $this->respond($request, $t->status === 'done' ? 'Hasil diterima. Task selesai.' : 'Dikembalikan untuk revisi.', ['status' => $t->status]);
    }

    public function time(TimeEntryRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        TaskTimeEntry::create(['task_id' => $task->id, 'user_id' => $request->user()->id, 'work_date' => $request->date('work_date'),
            'minutes_spent' => $request->integer('minutes_spent'), 'description' => $request->input('description')]);

        return $this->respond($request, 'Waktu kerja tercatat: '.$request->integer('minutes_spent').' menit.');
    }

    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $task);
        $task->delete();
        $this->workflow->log($task, $request->user(), 'deleted', 'Task dihapus (bisa dipulihkan)');

        return $this->respond($request, "Task {$task->title} dihapus.", ['deleted' => true]);
    }

    /** Download an attachment file: only users who can see its task. External links open directly. */
    public function download(Request $request, TaskAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $attachment->task);
        if ($attachment->external_url) {
            return redirect()->away($attachment->external_url);
        }
        // Shared disk: files uploaded in the Team App are readable in the Admin App and vice versa.
        $disk = Files::find($attachment->storage_disk, $attachment->storage_path);
        abort_if($disk === null, 404);

        return $disk->download($attachment->storage_path, $attachment->original_name ?: basename($attachment->storage_path));
    }

    /** JSON for the board, the side panel (I3), and other script callers; a redirect with a flash message for plain forms. */
    private function respond(Request $request, string $message, array $data = [], int $code = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message] + $data, $code);
        }

        return $code >= 400 ? back()->with('error', $message) : back()->with('ok', $message);
    }
}
