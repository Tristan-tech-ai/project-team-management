<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tim project (Petunjuk Teknis 3.1 dan 3.2):
 * - divisi project harus dari Company milik project;
 * - anggota unik (project_members unik per user), PIC otomatis anggota;
 * - anggota dipetakan ke divisi project lewat project_member_divisions sesuai keanggotaan divisinya, dihitung unik untuk total orang;
 * - PIC task dan assignee harus anggota aktif project; PIC task otomatis assignee.
 */
class ProjectTeamService
{
    public function aturTim(Project $project, array $divisionIds, array $memberIds, User $oleh): void
    {
        $divisionIds = array_values(array_unique(array_map('intval', $divisionIds)));
        $memberIds = array_values(array_unique(array_map('intval', array_merge($memberIds, [(int) $project->pic_user_id]))));

        $salah = DB::table('divisions')->whereIn('id', $divisionIds ?: [0])->where('company_id', '!=', $project->company_id)->count()
            + (count($divisionIds) - DB::table('divisions')->whereIn('id', $divisionIds ?: [0])->count());
        if ($salah > 0) {
            throw ValidationException::withMessages(['divisions' => 'Divisi project harus berasal dari company project ini.']);
        }
        $aktif = DB::table('company_members')->where('company_members.company_id', $project->company_id)->where('company_members.is_active', 1)
            ->join('user_profiles', 'user_profiles.user_id', '=', 'company_members.user_id')->where('user_profiles.is_active', 1)
            ->whereIn('company_members.user_id', $memberIds)->pluck('company_members.user_id')->map(fn ($n) => (int) $n)->all();
        $bukan = array_diff($memberIds, $aktif);
        if ($bukan !== []) {
            $nama = DB::table('users')->whereIn('id', $bukan)->pluck('name')->implode(', ');
            throw ValidationException::withMessages(['members' => "PIC dan anggota harus user aktif yang menjadi anggota company project: {$nama}."]);
        }

        $sekarang = now();
        // Divisi: yang tidak dipilih dilepas (tidak ada riwayat di tabel ini; keterlibatan orang tetap di project_members).
        DB::table('project_divisions')->where('project_id', $project->id)->whereNotIn('division_id', $divisionIds ?: [0])->delete();
        foreach ($divisionIds as $did) {
            DB::table('project_divisions')->updateOrInsert(['project_id' => $project->id, 'division_id' => $did], ['updated_at' => $sekarang, 'created_at' => $sekarang]);
        }
        // Anggota: yang dikeluarkan dinonaktifkan (is_active = 0), bukan dihapus, supaya riwayat penugasan tetap terbaca.
        DB::table('project_members')->where('project_id', $project->id)->whereNotIn('user_id', $memberIds)->update(['is_active' => 0, 'updated_at' => $sekarang]);
        $pd = DB::table('project_divisions')->where('project_id', $project->id)->pluck('id', 'division_id');
        foreach ($memberIds as $uid) {
            $ada = DB::table('project_members')->where('project_id', $project->id)->where('user_id', $uid)->first();
            if ($ada) {
                DB::table('project_members')->where('id', $ada->id)->update(['is_active' => 1, 'updated_at' => $sekarang]);
                $pmId = $ada->id;
            } else {
                $pmId = DB::table('project_members')->insertGetId(['project_id' => $project->id, 'user_id' => $uid, 'joined_at' => $sekarang, 'is_active' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
            }
            $divUser = DB::table('division_members')->where('user_id', $uid)->where('is_active', 1)->pluck('division_id')->map(fn ($n) => (int) $n)->all();
            $target = array_values(array_filter(array_map(fn ($d) => $pd[$d] ?? null, array_intersect($divUser, $divisionIds))));
            DB::table('project_member_divisions')->where('project_member_id', $pmId)->whereNotIn('project_division_id', $target ?: [0])->delete();
            foreach ($target as $pdId) {
                DB::table('project_member_divisions')->updateOrInsert(['project_member_id' => $pmId, 'project_division_id' => $pdId], ['updated_at' => $sekarang, 'created_at' => $sekarang]);
            }
        }
    }

    /** @return array{orang:int, divisi:int, penugasan:int} anggota unik vs jumlah penugasan (dokumen 11.2 Management). */
    public static function hitung(Project $project): array
    {
        return [
            'orang' => (int) DB::table('project_members')->where('project_id', $project->id)->where('is_active', 1)->distinct()->count('user_id'),
            'divisi' => (int) DB::table('project_divisions')->where('project_id', $project->id)->distinct()->count('division_id'),
            'penugasan' => (int) DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
                ->where('tasks.project_id', $project->id)->whereNull('tasks.deleted_at')->count(),
        ];
    }

    public function pastikanAnggotaProject(Project $project, array $userIds, string $kolom = 'assignees'): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $ada = DB::table('project_members')->where('project_id', $project->id)->where('is_active', 1)->whereIn('user_id', $userIds ?: [0])->pluck('user_id')->map(fn ($n) => (int) $n)->all();
        $bukan = array_diff($userIds, $ada);
        if ($bukan !== []) {
            throw ValidationException::withMessages([$kolom => 'Pelaksana dan PIC harus anggota aktif project ini.']);
        }
    }

    public function pastikanAssignee(Task $task, int $userId, User $oleh): void
    {
        TaskAssignee::query()->firstOrCreate(['task_id' => $task->id, 'user_id' => $userId], ['assigned_by' => $oleh->id, 'assigned_at' => now()]);
    }

    /** Mengatur PIC + assignee task. PIC selalu ikut assignee. Assignee yang dilepas dihapus dari task_assignees (dicatat di task_activities). */
    public function aturAssignee(Task $task, int $picId, array $assignees, User $oleh): void
    {
        $task->loadMissing('project');
        $semua = array_values(array_unique(array_map('intval', array_merge($assignees, [$picId]))));
        $this->pastikanAnggotaProject($task->project, $semua);
        DB::transaction(function () use ($task, $picId, $semua, $oleh) {
            $lama = DB::table('task_assignees')->where('task_id', $task->id)->pluck('user_id')->map(fn ($n) => (int) $n)->all();
            DB::table('task_assignees')->where('task_id', $task->id)->whereNotIn('user_id', $semua)->delete();
            foreach ($semua as $uid) {
                $this->pastikanAssignee($task, $uid, $oleh);
            }
            if ((int) $task->pic_user_id !== $picId) {
                $task->update(['pic_user_id' => $picId]);
            }
            if ($lama !== $semua) {
                app(TaskWorkflowService::class)->catat($task, $oleh, 'assignees_changed', 'Pelaksana diatur: '.DB::table('users')->whereIn('id', $semua)->pluck('name')->implode(', '));
            }
        });
    }
}
