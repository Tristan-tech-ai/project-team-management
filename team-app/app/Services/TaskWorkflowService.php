<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Support\Files;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Task status flow (Technical Guide 8.2 and 8.3). Every change uses a DB transaction and is logged in task_activities.
 * The backend decides the moves: members cannot skip Review or mark Done without approval.
 *   backlog -> in_progress (Start)            : assignee or project manager
 *   in_progress -> backlog                    : assignee or project manager
 *   in_progress -> review                     : only through submitResult (link/file + note)
 *   review -> done                            : reviewer, approving the pending result
 *   review -> in_progress                     : reviewer, revision note required
 *   done -> in_progress (reopen)              : reviewer; completed_at/completed_by are cleared
 */
class TaskWorkflowService
{
    public const ALLOWED = [
        'backlog' => ['in_progress'],
        'in_progress' => ['backlog', 'review'],
        'review' => ['done', 'in_progress'],
        'done' => ['in_progress'],
    ];

    /** One primary action per status (Technical Guide 6.3). */
    public static function primaryAction(Task $task): ?array
    {
        return match ($task->status) {
            'backlog' => ['to' => 'in_progress', 'label' => 'Mulai'],
            'in_progress' => ['to' => 'review', 'label' => 'Kirim untuk Review'],
            'review' => ['to' => 'done', 'label' => 'Terima atau revisi'],
            default => null,
        };
    }

    public function move(Task $task, string $to, User $user, ?string $notes = null): Task
    {
        if (! array_key_exists($to, self::ALLOWED)) {
            throw ValidationException::withMessages(['status' => 'Status tidak dikenal.']);
        }
        if ($task->parent_id !== null) {
            throw ValidationException::withMessages(['status' => 'Subtask dicentang lewat checklist, bukan dipindah kolom.']);
        }

        return DB::transaction(function () use ($task, $to, $user, $notes) {
            /** @var Task $t */
            $t = Task::query()->lockForUpdate()->findOrFail($task->id);
            $from = $t->status;
            if ($from === $to) {
                return $t;
            }
            if (! in_array($to, self::ALLOWED[$from] ?? [], true)) {
                $suggestion = $to === 'done' ? ' Kirim hasil untuk review dulu; Selesai hanya lewat persetujuan PIC atau manager.' : '';
                throw ValidationException::withMessages(['status' => 'Task tidak bisa pindah dari '.\App\Support\Codes::label(\App\Support\Codes::TASK_STATUS, $from).' ke '.\App\Support\Codes::label(\App\Support\Codes::TASK_STATUS, $to).'.'.$suggestion]);
            }
            $reviewer = Gate::forUser($user)->allows('review', $t);
            if ($to === 'review') {
                throw ValidationException::withMessages(['status' => 'Kirim hasil dulu lewat tombol Kirim untuk Review (tautan atau berkas dan catatan singkat).']);
            }
            if (in_array($from, ['review', 'done'], true) || $to === 'done') {
                if (! $reviewer) {
                    throw ValidationException::withMessages(['status' => 'Hanya PIC atau manager yang berizin review yang bisa menerima, merevisi, atau membuka kembali task.']);
                }
                if ($from === 'review' && $to === 'done') {
                    $sub = $t->submissions()->where('status', 'pending')->latest('id')->first();
                    if (! $sub) {
                        throw ValidationException::withMessages(['status' => 'Belum ada hasil yang menunggu review.']);
                    }

                    return $this->decide($sub, 'approved', $user, $notes);
                }
                if ($from === 'review' && $to === 'in_progress') {
                    $sub = $t->submissions()->where('status', 'pending')->latest('id')->first();
                    if ($sub) {
                        return $this->decide($sub, 'revision', $user, $notes);
                    }
                }
            } elseif (! Gate::forUser($user)->allows('moveStatus', $t)) {
                throw ValidationException::withMessages(['status' => 'Kamu tidak memegang task ini.']);
            }
            $update = ['status' => $to];
            if ($to !== 'done') {
                $update['completed_at'] = null;
                $update['completed_by'] = null;
            }
            if ($to === 'in_progress' && $t->start_at === null) {
                $update['start_at'] = now();
            }
            $t->update($update);
            $this->log($t, $user, 'status_changed', $from === 'done' ? 'Task dibuka kembali' : 'Status diubah', $from, $to);

            return $t->refresh();
        });
    }

    /** Submit a result: a link and/or a file + a note; the task moves to Review. Submission history is never deleted. */
    public function submitResult(Task $task, User $user, ?string $link, ?UploadedFile $file, ?string $notes): TaskSubmission
    {
        if (! $link && ! $file) {
            throw ValidationException::withMessages(['external_url' => 'Isi tautan hasil atau unggah berkas.']);
        }

        return DB::transaction(function () use ($task, $user, $link, $file, $notes) {
            $t = Task::query()->lockForUpdate()->findOrFail($task->id);
            if (! in_array($t->status, ['in_progress', 'backlog'], true)) {
                throw ValidationException::withMessages(['status' => 'Hasil hanya bisa dikirim saat task Antrian atau Dikerjakan.']);
            }
            if ($t->submissions()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['status' => 'Masih ada hasil yang menunggu review.']);
            }
            $sub = TaskSubmission::create(['task_id' => $t->id, 'submitted_by' => $user->id, 'submission_note' => $notes, 'status' => 'pending', 'submitted_at' => now()]);
            if ($link) {
                TaskAttachment::create(['task_id' => $t->id, 'task_submission_id' => $sub->id, 'uploaded_by' => $user->id, 'external_url' => $link, 'original_name' => null]);
            }
            if ($file) {
                // Shared disk: files from the Team App are stored in admin-app storage and readable in both apps.
                $path = $file->store('attachments/task-'.$t->id, Files::DISK);
                if ($path === false) {
                    throw ValidationException::withMessages(['file' => 'Berkas gagal disimpan. Coba unggah lagi.']);
                }
                TaskAttachment::create(['task_id' => $t->id, 'task_submission_id' => $sub->id, 'uploaded_by' => $user->id,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255), 'storage_disk' => Files::DISK, 'storage_path' => $path,
                    'mime_type' => $file->getMimeType(), 'file_size_bytes' => $file->getSize()]);
            }
            $from = $t->status;
            $t->update(['status' => 'review', 'completed_at' => null, 'completed_by' => null, 'start_at' => $t->start_at ?? now()]);
            $this->log($t, $user, 'submitted', 'Hasil dikirim untuk review', $from, 'review', ['submission_id' => $sub->id]);

            return $sub;
        });
    }

    /** Approve (approved -> done) or request a revision (revision -> in_progress, note required). */
    public function decide(TaskSubmission $sub, string $decision, User $user, ?string $notes): Task
    {
        if ($decision === 'revision' && mb_strlen(trim((string) $notes)) < 5) {
            throw ValidationException::withMessages(['review_note' => 'Tulis alasan revisi (minimal 5 karakter) supaya pelaksana tahu yang perlu diperbaiki.']);
        }

        return DB::transaction(function () use ($sub, $decision, $user, $notes) {
            $s = TaskSubmission::query()->lockForUpdate()->findOrFail($sub->id);
            $t = Task::query()->lockForUpdate()->findOrFail($s->task_id);
            if (! Gate::forUser($user)->allows('review', $t)) {
                throw ValidationException::withMessages(['review_note' => 'Kamu tidak berizin meninjau task ini.']);
            }
            if ($s->status !== 'pending' || $t->status !== 'review') {
                throw ValidationException::withMessages(['review_note' => 'Hasil ini sudah diputuskan.']);
            }
            $s->update(['status' => $decision, 'review_note' => $notes, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            if ($decision === 'approved') {
                $t->update(['status' => 'done', 'completed_at' => now(), 'completed_by' => $user->id]);
                $this->log($t, $user, 'approved', 'Hasil diterima, task selesai', 'review', 'done', ['submission_id' => $s->id]);
            } else {
                $t->update(['status' => 'in_progress', 'completed_at' => null, 'completed_by' => null]);
                $this->log($t, $user, 'revision_requested', 'Diminta revisi: '.$notes, 'review', 'in_progress', ['submission_id' => $s->id]);
            }

            return $t->refresh();
        });
    }

    /** Subtask checklist: ticked in place, one level deep. A done subtask fills completed_at/by. */
    public function toggleSubtask(Task $subtask, User $user, bool $done): Task
    {
        if ($subtask->parent_id === null) {
            throw ValidationException::withMessages(['status' => 'Ini task utama, bukan checklist.']);
        }

        return DB::transaction(function () use ($subtask, $user, $done) {
            $s = Task::query()->lockForUpdate()->findOrFail($subtask->id);
            $parent = Task::query()->findOrFail($s->parent_id);
            if (! (Gate::forUser($user)->allows('moveStatus', $parent) || Gate::forUser($user)->allows('update', $parent))) {
                throw ValidationException::withMessages(['status' => 'Hanya pelaksana task atau pengelola project yang bisa mencentang checklist.']);
            }
            $to = $done ? 'done' : 'backlog';
            if ($s->status !== $to) {
                $s->update(['status' => $to, 'completed_at' => $done ? now() : null, 'completed_by' => $done ? $user->id : null]);
                $this->log($parent, $user, 'subtask_toggled', ($done ? 'Checklist selesai: ' : 'Checklist dibuka: ').$s->title, null, null, ['subtask_id' => $s->id]);
            }
            // Ticking an item means the work has started: a task still in the queue moves to In progress (same rules as dragging it).
            if ($done && $parent->status === 'backlog' && Gate::forUser($user)->allows('moveStatus', $parent)) {
                $this->move($parent, 'in_progress', $user);
            }

            return $s->refresh();
        });
    }

    public function log(Task $task, User $user, string $kind, string $description, ?string $old = null, ?string $new = null, array $meta = []): void
    {
        TaskActivity::create(['task_id' => $task->id, 'user_id' => $user->id, 'activity_type' => $kind, 'description' => mb_substr($description, 0, 1000),
            'old_value' => $old, 'new_value' => $new, 'metadata' => $meta ?: null]);
    }

    /** Task progress = done subtasks / total subtasks (Technical Guide 3.2). */
    public static function taskProgress(int $done, int $total): int
    {
        return $total === 0 ? 0 : (int) round($done * 100 / $total);
    }
}
