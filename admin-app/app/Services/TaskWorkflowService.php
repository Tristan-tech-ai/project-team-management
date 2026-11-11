<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAttachment;
use App\Models\TaskSubmission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Alur status task (Petunjuk Teknis 8.2 dan 8.3). Semua perubahan memakai DB transaction dan dicatat di task_activities.
 * Backend yang menentukan perpindahan: anggota tidak bisa melewati Review atau menandai Selesai tanpa persetujuan.
 *   backlog -> in_progress (Mulai)            : pelaksana atau pengelola
 *   in_progress -> backlog                    : pelaksana atau pengelola
 *   in_progress -> review                     : hanya lewat kirimHasil (link/berkas + catatan)
 *   review -> done                            : reviewer, menerima hasil yang menunggu
 *   review -> in_progress                     : reviewer, wajib catatan revisi
 *   done -> in_progress (buka kembali)        : reviewer; completed_at/completed_by dikosongkan
 */
class TaskWorkflowService
{
    public const BOLEH = [
        'backlog' => ['in_progress'],
        'in_progress' => ['backlog', 'review'],
        'review' => ['done', 'in_progress'],
        'done' => ['in_progress'],
    ];

    /** Satu aksi utama per status (Petunjuk Teknis 6.3). */
    public static function aksiUtama(Task $task): ?array
    {
        return match ($task->status) {
            'backlog' => ['ke' => 'in_progress', 'label' => 'Mulai'],
            'in_progress' => ['ke' => 'review', 'label' => 'Kirim untuk Review'],
            'review' => ['ke' => 'done', 'label' => 'Terima atau revisi'],
            default => null,
        };
    }

    public function pindah(Task $task, string $ke, User $user, ?string $catatan = null): Task
    {
        if (! array_key_exists($ke, self::BOLEH)) {
            throw ValidationException::withMessages(['status' => 'Status tidak dikenal.']);
        }
        if ($task->parent_id !== null) {
            throw ValidationException::withMessages(['status' => 'Subtask dicentang lewat checklist, bukan dipindah kolom.']);
        }

        return DB::transaction(function () use ($task, $ke, $user, $catatan) {
            /** @var Task $t */
            $t = Task::query()->lockForUpdate()->findOrFail($task->id);
            $dari = $t->status;
            if ($dari === $ke) {
                return $t;
            }
            if (! in_array($ke, self::BOLEH[$dari] ?? [], true)) {
                $saran = $ke === 'done' ? ' Kirim hasil untuk review dulu; Selesai hanya lewat persetujuan PIC atau manager.' : '';
                throw ValidationException::withMessages(['status' => 'Task tidak bisa pindah dari '.\App\Support\Kode::label(\App\Support\Kode::TASK_STATUS, $dari).' ke '.\App\Support\Kode::label(\App\Support\Kode::TASK_STATUS, $ke).'.'.$saran]);
            }
            $reviewer = Gate::forUser($user)->allows('review', $t);
            if ($ke === 'review') {
                throw ValidationException::withMessages(['status' => 'Kirim hasil dulu lewat tombol Kirim untuk Review (tautan atau berkas dan catatan singkat).']);
            }
            if (in_array($dari, ['review', 'done'], true) || $ke === 'done') {
                if (! $reviewer) {
                    throw ValidationException::withMessages(['status' => 'Hanya PIC atau manager yang berizin review yang bisa menerima, merevisi, atau membuka kembali task.']);
                }
                if ($dari === 'review' && $ke === 'done') {
                    $sub = $t->submissions()->where('status', 'pending')->latest('id')->first();
                    if (! $sub) {
                        throw ValidationException::withMessages(['status' => 'Belum ada hasil yang menunggu review.']);
                    }

                    return $this->putuskan($sub, 'approved', $user, $catatan);
                }
                if ($dari === 'review' && $ke === 'in_progress') {
                    $sub = $t->submissions()->where('status', 'pending')->latest('id')->first();
                    if ($sub) {
                        return $this->putuskan($sub, 'revision', $user, $catatan);
                    }
                }
            } elseif (! Gate::forUser($user)->allows('moveStatus', $t)) {
                throw ValidationException::withMessages(['status' => 'Kamu tidak memegang task ini.']);
            }
            $ubah = ['status' => $ke];
            if ($ke !== 'done') {
                $ubah['completed_at'] = null;
                $ubah['completed_by'] = null;
            }
            if ($ke === 'in_progress' && $t->start_at === null) {
                $ubah['start_at'] = now();
            }
            $t->update($ubah);
            $this->catat($t, $user, 'status_changed', $dari === 'done' ? 'Task dibuka kembali' : 'Status diubah', $dari, $ke);

            return $t->refresh();
        });
    }

    /** Kirim hasil: link dan/atau berkas + catatan; task masuk Review. Riwayat submission tidak dihapus. */
    public function kirimHasil(Task $task, User $user, ?string $tautan, ?UploadedFile $berkas, ?string $catatan): TaskSubmission
    {
        if (! $tautan && ! $berkas) {
            throw ValidationException::withMessages(['external_url' => 'Isi tautan hasil atau unggah berkas.']);
        }

        return DB::transaction(function () use ($task, $user, $tautan, $berkas, $catatan) {
            $t = Task::query()->lockForUpdate()->findOrFail($task->id);
            if (! in_array($t->status, ['in_progress', 'backlog'], true)) {
                throw ValidationException::withMessages(['status' => 'Hasil hanya bisa dikirim saat task Antrian atau Dikerjakan.']);
            }
            if ($t->submissions()->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['status' => 'Masih ada hasil yang menunggu review.']);
            }
            $sub = TaskSubmission::create(['task_id' => $t->id, 'submitted_by' => $user->id, 'submission_note' => $catatan, 'status' => 'pending', 'submitted_at' => now()]);
            if ($tautan) {
                TaskAttachment::create(['task_id' => $t->id, 'task_submission_id' => $sub->id, 'uploaded_by' => $user->id, 'external_url' => $tautan, 'original_name' => null]);
            }
            if ($berkas) {
                $path = $berkas->store('lampiran/task-'.$t->id, 'local');
                TaskAttachment::create(['task_id' => $t->id, 'task_submission_id' => $sub->id, 'uploaded_by' => $user->id,
                    'original_name' => mb_substr($berkas->getClientOriginalName(), 0, 255), 'storage_disk' => 'local', 'storage_path' => $path,
                    'mime_type' => $berkas->getMimeType(), 'file_size_bytes' => $berkas->getSize()]);
            }
            $dari = $t->status;
            $t->update(['status' => 'review', 'completed_at' => null, 'completed_by' => null, 'start_at' => $t->start_at ?? now()]);
            $this->catat($t, $user, 'submitted', 'Hasil dikirim untuk review', $dari, 'review', ['submission_id' => $sub->id]);

            return $sub;
        });
    }

    /** Terima (approved -> done) atau revisi (revision -> in_progress, catatan wajib). */
    public function putuskan(TaskSubmission $sub, string $keputusan, User $user, ?string $catatan): Task
    {
        if ($keputusan === 'revision' && mb_strlen(trim((string) $catatan)) < 5) {
            throw ValidationException::withMessages(['review_note' => 'Tulis alasan revisi (minimal 5 karakter) supaya pelaksana tahu yang perlu diperbaiki.']);
        }

        return DB::transaction(function () use ($sub, $keputusan, $user, $catatan) {
            $s = TaskSubmission::query()->lockForUpdate()->findOrFail($sub->id);
            $t = Task::query()->lockForUpdate()->findOrFail($s->task_id);
            if (! Gate::forUser($user)->allows('review', $t)) {
                throw ValidationException::withMessages(['review_note' => 'Kamu tidak berizin meninjau task ini.']);
            }
            if ($s->status !== 'pending' || $t->status !== 'review') {
                throw ValidationException::withMessages(['review_note' => 'Hasil ini sudah diputuskan.']);
            }
            $s->update(['status' => $keputusan, 'review_note' => $catatan, 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
            if ($keputusan === 'approved') {
                $t->update(['status' => 'done', 'completed_at' => now(), 'completed_by' => $user->id]);
                $this->catat($t, $user, 'approved', 'Hasil diterima, task selesai', 'review', 'done', ['submission_id' => $s->id]);
            } else {
                $t->update(['status' => 'in_progress', 'completed_at' => null, 'completed_by' => null]);
                $this->catat($t, $user, 'revision_requested', 'Diminta revisi: '.$catatan, 'review', 'in_progress', ['submission_id' => $s->id]);
            }

            return $t->refresh();
        });
    }

    /** Checklist subtask: centang langsung, satu tingkat. Subtask selesai mengisi completed_at/by. */
    public function centang(Task $subtask, User $user, bool $selesai): Task
    {
        if ($subtask->parent_id === null) {
            throw ValidationException::withMessages(['status' => 'Ini task utama, bukan checklist.']);
        }

        return DB::transaction(function () use ($subtask, $user, $selesai) {
            $s = Task::query()->lockForUpdate()->findOrFail($subtask->id);
            $induk = Task::query()->findOrFail($s->parent_id);
            if (! (Gate::forUser($user)->allows('moveStatus', $induk) || Gate::forUser($user)->allows('update', $induk))) {
                throw ValidationException::withMessages(['status' => 'Hanya pelaksana task atau pengelola project yang bisa mencentang checklist.']);
            }
            $ke = $selesai ? 'done' : 'backlog';
            if ($s->status !== $ke) {
                $s->update(['status' => $ke, 'completed_at' => $selesai ? now() : null, 'completed_by' => $selesai ? $user->id : null]);
                $this->catat($induk, $user, 'subtask_toggled', ($selesai ? 'Checklist selesai: ' : 'Checklist dibuka: ').$s->title, null, null, ['subtask_id' => $s->id]);
            }

            return $s->refresh();
        });
    }

    public function catat(Task $task, User $user, string $jenis, string $ket, ?string $lama = null, ?string $baru = null, array $meta = []): void
    {
        TaskActivity::create(['task_id' => $task->id, 'user_id' => $user->id, 'activity_type' => $jenis, 'description' => mb_substr($ket, 0, 1000),
            'old_value' => $lama, 'new_value' => $baru, 'metadata' => $meta ?: null]);
    }

    /** Progress task = subtask selesai / jumlah subtask (Petunjuk Teknis 3.2). */
    public static function progresTask(int $selesai, int $total): int
    {
        return $total === 0 ? 0 : (int) round($selesai * 100 / $total);
    }
}
