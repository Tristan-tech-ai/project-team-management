<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ScheduleChangeRequest;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Perubahan deadline (Petunjuk Teknis 8.4): pengajuan pending -> approved/rejected oleh PIC berizin atau Manager.
 * Hanya approved yang mengubah due_at, di dalam transaksi dengan kunci baris, dan mencatat applied_at.
 * baseline_due_at TIDAK pernah diubah. Menambah rapat/kegiatan tidak otomatis menggeser deadline.
 */
class ScheduleChangeService
{
    public function ajukan(Project $project, ?Task $task, ?ProjectActivity $activity, Carbon $usulan, string $alasan, User $oleh): ScheduleChangeRequest
    {
        if ($task && (int) $task->project_id !== (int) $project->id) {
            throw ValidationException::withMessages(['task_id' => 'Task bukan milik project ini.']);
        }
        if ($activity && (int) $activity->project_id !== (int) $project->id) {
            throw ValidationException::withMessages(['project_activity_id' => 'Aktivitas bukan milik project ini.']);
        }
        $target = $task ?? $project;
        if (ScheduleChangeRequest::query()->where('project_id', $project->id)->where('task_id', $task?->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['proposed_due_at' => 'Masih ada pengajuan yang menunggu keputusan untuk '.($task ? 'task' : 'project').' ini.']);
        }

        return DB::transaction(function () use ($project, $task, $activity, $usulan, $alasan, $oleh, $target) {
            $r = ScheduleChangeRequest::create([
                'project_id' => $project->id, 'task_id' => $task?->id, 'project_activity_id' => $activity?->id, 'requested_by' => $oleh->id,
                'previous_due_at' => $target->due_at, 'proposed_due_at' => $usulan, 'reason' => $alasan, 'status' => 'pending', 'requested_at' => now(),
            ]);
            if ($task) {
                TaskActivity::create(['task_id' => $task->id, 'user_id' => $oleh->id, 'activity_type' => 'schedule_requested',
                    'description' => 'Pengajuan geser deadline: '.$alasan, 'old_value' => $task->due_at?->toIso8601String(), 'new_value' => $usulan->toIso8601String()]);
            }

            return $r;
        });
    }

    public function bolehPutuskan(User $user, ScheduleChangeRequest $r): bool
    {
        $r->loadMissing(['project', 'task']);

        return $r->task ? Gate::forUser($user)->allows('approveSchedule', $r->task) : Gate::forUser($user)->allows('approveSchedule', $r->project);
    }

    public function putuskan(ScheduleChangeRequest $r, bool $setuju, ?string $catatan, User $oleh): ScheduleChangeRequest
    {
        if (! $this->bolehPutuskan($oleh, $r)) {
            throw ValidationException::withMessages(['decision' => 'Kamu tidak berizin memutuskan perubahan jadwal ini.']);
        }
        if (! $setuju && mb_strlen(trim((string) $catatan)) < 5) {
            throw ValidationException::withMessages(['decision_note' => 'Tulis alasan penolakan (minimal 5 karakter).']);
        }

        return DB::transaction(function () use ($r, $setuju, $catatan, $oleh) {
            $k = ScheduleChangeRequest::query()->lockForUpdate()->findOrFail($r->id);
            if ($k->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan.']);
            }
            $k->update(['status' => $setuju ? 'approved' : 'rejected', 'decided_by' => $oleh->id, 'decision_note' => $catatan, 'decided_at' => now()]);
            if ($setuju) {
                $target = $k->task_id ? Task::query()->lockForUpdate()->findOrFail($k->task_id) : Project::query()->lockForUpdate()->findOrFail($k->project_id);
                // Baseline tetap: bila baseline belum pernah diisi, target lama menjadi baseline supaya deviasi tetap terbaca.
                $ubah = ['due_at' => $k->proposed_due_at];
                if ($target->baseline_due_at === null && $k->previous_due_at !== null) {
                    $ubah['baseline_due_at'] = $k->previous_due_at;
                }
                $target->update($ubah);
                $k->update(['applied_at' => now()]);
            }
            if ($k->task_id) {
                TaskActivity::create(['task_id' => $k->task_id, 'user_id' => $oleh->id, 'activity_type' => $setuju ? 'schedule_approved' : 'schedule_rejected',
                    'description' => ($setuju ? 'Deadline baru disetujui' : 'Pengajuan deadline ditolak').($catatan ? ': '.$catatan : ''),
                    'old_value' => $k->previous_due_at?->toIso8601String(), 'new_value' => $k->proposed_due_at?->toIso8601String()]);
            }

            return $k->refresh();
        });
    }
}
