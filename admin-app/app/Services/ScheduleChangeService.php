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
 * Deadline changes (Technical Guide 8.4): pending request -> approved/rejected by an authorized PIC or a Manager.
 * Only approved requests change due_at, inside a transaction with a row lock, and record applied_at.
 * baseline_due_at is NEVER changed. Adding a meeting/activity never shifts the deadline automatically.
 */
class ScheduleChangeService
{
    public function propose(Project $project, ?Task $task, ?ProjectActivity $activity, Carbon $proposal, string $reason, User $by): ScheduleChangeRequest
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

        return DB::transaction(function () use ($project, $task, $activity, $proposal, $reason, $by, $target) {
            $r = ScheduleChangeRequest::create([
                'project_id' => $project->id, 'task_id' => $task?->id, 'project_activity_id' => $activity?->id, 'requested_by' => $by->id,
                'previous_due_at' => $target->due_at, 'proposed_due_at' => $proposal, 'reason' => $reason, 'status' => 'pending', 'requested_at' => now(),
            ]);
            if ($task) {
                TaskActivity::create(['task_id' => $task->id, 'user_id' => $by->id, 'activity_type' => 'schedule_requested',
                    'description' => 'Pengajuan geser deadline: '.$reason, 'old_value' => $task->due_at?->toIso8601String(), 'new_value' => $proposal->toIso8601String()]);
            }

            return $r;
        });
    }

    public function canDecide(User $user, ScheduleChangeRequest $r): bool
    {
        $r->loadMissing(['project', 'task']);

        return $r->task ? Gate::forUser($user)->allows('approveSchedule', $r->task) : Gate::forUser($user)->allows('approveSchedule', $r->project);
    }

    public function decide(ScheduleChangeRequest $r, bool $approve, ?string $notes, User $by): ScheduleChangeRequest
    {
        if (! $this->canDecide($by, $r)) {
            throw ValidationException::withMessages(['decision' => 'Kamu tidak berizin memutuskan perubahan jadwal ini.']);
        }
        if (! $approve && mb_strlen(trim((string) $notes)) < 5) {
            throw ValidationException::withMessages(['decision_note' => 'Tulis alasan penolakan (minimal 5 karakter).']);
        }

        return DB::transaction(function () use ($r, $approve, $notes, $by) {
            $k = ScheduleChangeRequest::query()->lockForUpdate()->findOrFail($r->id);
            if ($k->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Pengajuan ini sudah diputuskan.']);
            }
            $k->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $by->id, 'decision_note' => $notes, 'decided_at' => now()]);
            if ($approve) {
                $target = $k->task_id ? Task::query()->lockForUpdate()->findOrFail($k->task_id) : Project::query()->lockForUpdate()->findOrFail($k->project_id);
                // The baseline stays: when no baseline was ever set, the old target becomes the baseline so the deviation stays readable.
                $update = ['due_at' => $k->proposed_due_at];
                if ($target->baseline_due_at === null && $k->previous_due_at !== null) {
                    $update['baseline_due_at'] = $k->previous_due_at;
                }
                $target->update($update);
                $k->update(['applied_at' => now()]);
            }
            if ($k->task_id) {
                TaskActivity::create(['task_id' => $k->task_id, 'user_id' => $by->id, 'activity_type' => $approve ? 'schedule_approved' : 'schedule_rejected',
                    'description' => ($approve ? 'Deadline baru disetujui' : 'Pengajuan deadline ditolak').($notes ? ': '.$notes : ''),
                    'old_value' => $k->previous_due_at?->toIso8601String(), 'new_value' => $k->proposed_due_at?->toIso8601String()]);
            }

            return $k->refresh();
        });
    }
}
