<?php

namespace App\Http\Controllers\Bersama;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kerja\AssigneeRequest;
use App\Http\Requests\Kerja\QuickTaskRequest;
use App\Http\Requests\Kerja\ReviewRequest;
use App\Http\Requests\Kerja\StatusRequest;
use App\Http\Requests\Kerja\SubmissionRequest;
use App\Http\Requests\Kerja\SubtaskRequest;
use App\Http\Requests\Kerja\TaskUpdateRequest;
use App\Http\Requests\Kerja\TimeEntryRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskSubmission;
use App\Models\TaskTemplate;
use App\Models\TaskTimeEntry;
use App\Services\ProjectTeamService;
use App\Services\ProjectTemplateService;
use App\Services\TaskWorkflowService;
use App\Support\Kode;
use App\Support\Waktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Endpoint task yang sama persis di admin-app dan team-app (Petunjuk Teknis 9.2):
 * POST /projects/{id}/tasks, PATCH /tasks/{id}/status, PATCH /subtasks/{id}/toggle, POST /tasks/{id}/submissions,
 * POST /submissions/{id}/review, plus ubah task, pelaksana, subtask, dan waktu kerja.
 */
class TaskAksiController extends Controller
{
    public function __construct(private TaskWorkflowService $alur, private ProjectTeamService $tim) {}

    public function store(QuickTaskRequest $request, Project $project): JsonResponse|RedirectResponse
    {
        $d = $request->validated();
        $assignees = array_values(array_unique(array_map('intval', array_merge($d['assignees'] ?? [], [(int) $d['pic_user_id']]))));
        $this->tim->pastikanAnggotaProject($project, $assignees);
        $periode = $d['project_period_id'] ?? $project->periods()->where('status', 'active')->orderByDesc('starts_at')->value('id');
        $due = Waktu::dariInput($d['due_at'] ?? null);
        $isi = ['project_id' => $project->id, 'project_period_id' => $periode, 'parent_id' => null, 'pic_user_id' => (int) $d['pic_user_id'],
            'created_by' => $request->user()->id, 'title' => $d['title'], 'description' => $d['description'] ?? null,
            'task_type' => $d['task_type'] ?? 'general', 'status' => 'backlog', 'priority' => $d['priority'] ?? 'normal',
            'baseline_due_at' => $due, 'due_at' => $due,
            'sort_order' => (int) Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('status', 'backlog')->max('sort_order') + 1];
        $task = DB::transaction(function () use ($d, $isi, $project, $assignees, $request) {
            $task = ! empty($d['task_template_id'])
                ? app(ProjectTemplateService::class)->terapkanTemplateTask($project, TaskTemplate::query()->findOrFail($d['task_template_id']), $isi, $request->user())
                : Task::create($isi);
            foreach ($assignees as $uid) {
                $this->tim->pastikanAssignee($task, $uid, $request->user());
            }
            $this->alur->catat($task, $request->user(), 'created', 'Task dibuat');

            return $task;
        });

        return $this->jawab($request, "Task {$task->title} ditambahkan ke Antrian.", ['task_id' => $task->id]);
    }

    public function update(TaskUpdateRequest $request, Task $task): RedirectResponse
    {
        $d = $request->validated();
        foreach (['scheduled_publish_at', 'published_at'] as $k) {
            $d[$k] = Waktu::dariInput($d[$k] ?? null);
        }
        if (array_key_exists('due_at', $d)) {
            $d['due_at'] = Waktu::dariInput($d['due_at']);
            $d['baseline_due_at'] = $d['due_at'];
        }
        $task->update($d);
        $this->alur->catat($task, $request->user(), 'updated', 'Isi task diubah');

        return back()->with('ok', 'Task tersimpan.');
    }

    public function status(StatusRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $t = $this->alur->pindah($task, $request->input('status'), $request->user(), $request->input('catatan'));

        return $this->jawab($request, 'Status: '.Kode::label(Kode::TASK_STATUS, $t->status).'.', ['status' => $t->status, 'label' => Kode::label(Kode::TASK_STATUS, $t->status)]);
    }

    public function assignees(AssigneeRequest $request, Task $task): RedirectResponse
    {
        $this->tim->aturAssignee($task, (int) $request->input('pic_user_id'), (array) $request->input('assignees', []), $request->user());

        return back()->with('ok', 'PIC dan pelaksana tersimpan.');
    }

    public function subtask(SubtaskRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        if ($task->parent_id !== null) {
            return $this->jawab($request, 'Subtask hanya satu tingkat: tambahkan ke task utamanya.', [], 422);
        }
        $s = Task::create(['project_id' => $task->project_id, 'project_period_id' => $task->project_period_id, 'parent_id' => $task->id,
            'pic_user_id' => $task->pic_user_id, 'created_by' => $request->user()->id, 'title' => $request->input('title'), 'status' => 'backlog',
            'sort_order' => (int) $task->subtasks()->max('sort_order') + 1]);
        $this->alur->catat($task, $request->user(), 'subtask_added', 'Checklist ditambah: '.$s->title);

        return $this->jawab($request, 'Checklist ditambah.', ['subtask_id' => $s->id]);
    }

    public function toggle(Request $request, Task $subtask): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $subtask);
        $s = $this->alur->centang($subtask, $request->user(), $request->boolean('selesai', $subtask->status !== 'done'));
        $induk = $s->parent_id;
        $total = Task::query()->where('parent_id', $induk)->count();
        $selesai = Task::query()->where('parent_id', $induk)->where('status', 'done')->count();

        return $this->jawab($request, $s->status === 'done' ? 'Checklist selesai.' : 'Checklist dibuka lagi.',
            ['selesai' => $s->status === 'done', 'progres' => TaskWorkflowService::progresTask($selesai, $total), 'jumlah' => "{$selesai} / {$total}"]);
    }

    public function submit(SubmissionRequest $request, Task $task): JsonResponse|RedirectResponse
    {
        $this->alur->kirimHasil($task, $request->user(), $request->input('external_url'), $request->file('berkas'), $request->input('submission_note'));

        return $this->jawab($request, 'Hasil terkirim. Task masuk Review.');
    }

    public function review(ReviewRequest $request, TaskSubmission $submission): JsonResponse|RedirectResponse
    {
        $t = $this->alur->putuskan($submission, $request->input('keputusan'), $request->user(), $request->input('review_note'));

        return $this->jawab($request, $t->status === 'done' ? 'Hasil diterima. Task selesai.' : 'Dikembalikan untuk revisi.', ['status' => $t->status]);
    }

    public function waktu(TimeEntryRequest $request, Task $task): RedirectResponse
    {
        TaskTimeEntry::create(['task_id' => $task->id, 'user_id' => $request->user()->id, 'work_date' => $request->date('work_date'),
            'minutes_spent' => $request->integer('minutes_spent'), 'description' => $request->input('description')]);

        return back()->with('ok', 'Waktu kerja tercatat: '.$request->integer('minutes_spent').' menit.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $task->delete();
        $this->alur->catat($task, $request->user(), 'deleted', 'Task dihapus (bisa dipulihkan)');

        return redirect()->back()->with('ok', "Task {$task->title} dihapus.");
    }

    /** Unduh lampiran berkas: hanya yang bisa melihat task-nya. Tautan luar dibuka langsung. */
    public function unduh(Request $request, TaskAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $this->authorize('view', $attachment->task);
        if ($attachment->external_url) {
            return redirect()->away($attachment->external_url);
        }
        abort_unless($attachment->storage_path && Storage::disk($attachment->storage_disk ?: 'local')->exists($attachment->storage_path), 404);

        return Storage::disk($attachment->storage_disk ?: 'local')->download($attachment->storage_path, $attachment->original_name ?: basename($attachment->storage_path));
    }

    private function jawab(Request $request, string $pesan, array $data = [], int $kode = 200): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $pesan] + $data, $kode);
        }

        return $kode >= 400 ? back()->with('galat', $pesan) : back()->with('ok', $pesan);
    }
}
