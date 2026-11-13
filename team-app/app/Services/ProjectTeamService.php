<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Project team (Technical Guide 3.1 and 3.2):
 * - project divisions must belong to the project company;
 * - unique members (project_members unique per user), the PIC is automatically a member;
 * - members are mapped to project divisions through project_member_divisions by their division membership, counted once for the people total;
 * - task PIC and assignees must be active project members; the task PIC is automatically an assignee.
 */
class ProjectTeamService
{
    public function setTeam(Project $project, array $divisionIds, array $memberIds, User $by): void
    {
        $divisionIds = array_values(array_unique(array_map('intval', $divisionIds)));
        $memberIds = array_values(array_unique(array_map('intval', array_merge($memberIds, [(int) $project->pic_user_id]))));

        $wrong = DB::table('divisions')->whereIn('id', $divisionIds ?: [0])->where('company_id', '!=', $project->company_id)->count()
            + (count($divisionIds) - DB::table('divisions')->whereIn('id', $divisionIds ?: [0])->count());
        if ($wrong > 0) {
            throw ValidationException::withMessages(['divisions' => 'Divisi project harus berasal dari company project ini.']);
        }
        $active = DB::table('company_members')->where('company_members.company_id', $project->company_id)->where('company_members.is_active', 1)
            ->join('user_profiles', 'user_profiles.user_id', '=', 'company_members.user_id')->where('user_profiles.is_active', 1)
            ->whereIn('company_members.user_id', $memberIds)->pluck('company_members.user_id')->map(fn ($n) => (int) $n)->all();
        $nonMemberIds = array_diff($memberIds, $active);
        if ($nonMemberIds !== []) {
            $name = DB::table('users')->whereIn('id', $nonMemberIds)->pluck('name')->implode(', ');
            throw ValidationException::withMessages(['members' => "PIC dan anggota harus user aktif yang menjadi anggota company project: {$name}."]);
        }

        $now = now();
        // Divisions that are not selected are released (this table keeps no history; people stay recorded in project_members).
        DB::table('project_divisions')->where('project_id', $project->id)->whereNotIn('division_id', $divisionIds ?: [0])->delete();
        foreach ($divisionIds as $did) {
            DB::table('project_divisions')->updateOrInsert(['project_id' => $project->id, 'division_id' => $did], ['updated_at' => $now, 'created_at' => $now]);
        }
        // Removed members are deactivated (is_active = 0), not deleted, so assignment history stays readable.
        DB::table('project_members')->where('project_id', $project->id)->whereNotIn('user_id', $memberIds)->update(['is_active' => 0, 'updated_at' => $now]);
        $pd = DB::table('project_divisions')->where('project_id', $project->id)->pluck('id', 'division_id');
        foreach ($memberIds as $uid) {
            $exists = DB::table('project_members')->where('project_id', $project->id)->where('user_id', $uid)->first();
            if ($exists) {
                DB::table('project_members')->where('id', $exists->id)->update(['is_active' => 1, 'updated_at' => $now]);
                $pmId = $exists->id;
            } else {
                $pmId = DB::table('project_members')->insertGetId(['project_id' => $project->id, 'user_id' => $uid, 'joined_at' => $now, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
            $userDivisions = DB::table('division_members')->where('user_id', $uid)->where('is_active', 1)->pluck('division_id')->map(fn ($n) => (int) $n)->all();
            $target = array_values(array_filter(array_map(fn ($d) => $pd[$d] ?? null, array_intersect($userDivisions, $divisionIds))));
            DB::table('project_member_divisions')->where('project_member_id', $pmId)->whereNotIn('project_division_id', $target ?: [0])->delete();
            foreach ($target as $pdId) {
                DB::table('project_member_divisions')->updateOrInsert(['project_member_id' => $pmId, 'project_division_id' => $pdId], ['updated_at' => $now, 'created_at' => $now]);
            }
        }
    }

    /** @return array{people:int, divisions:int, assignments:int} unique members vs number of assignments (guide 11.2 Management). */
    public static function counts(Project $project): array
    {
        return [
            'people' => (int) DB::table('project_members')->where('project_id', $project->id)->where('is_active', 1)->distinct()->count('user_id'),
            'divisions' => (int) DB::table('project_divisions')->where('project_id', $project->id)->distinct()->count('division_id'),
            'assignments' => (int) DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
                ->where('tasks.project_id', $project->id)->whereNull('tasks.deleted_at')->count(),
        ];
    }

    public function ensureProjectMembers(Project $project, array $userIds, string $columns = 'assignees'): void
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $exists = DB::table('project_members')->where('project_id', $project->id)->where('is_active', 1)->whereIn('user_id', $userIds ?: [0])->pluck('user_id')->map(fn ($n) => (int) $n)->all();
        $nonMemberIds = array_diff($userIds, $exists);
        if ($nonMemberIds !== []) {
            throw ValidationException::withMessages([$columns => 'Pelaksana dan PIC harus anggota aktif project ini.']);
        }
    }

    public function ensureAssignee(Task $task, int $userId, User $by): void
    {
        TaskAssignee::query()->firstOrCreate(['task_id' => $task->id, 'user_id' => $userId], ['assigned_by' => $by->id, 'assigned_at' => now()]);
    }

    /** Sets the task PIC + assignees. The PIC is always an assignee. Released assignees are removed from task_assignees (logged in task_activities). */
    public function setAssignees(Task $task, int $picId, array $assignees, User $by): void
    {
        $task->loadMissing('project');
        $all = array_values(array_unique(array_map('intval', array_merge($assignees, [$picId]))));
        $this->ensureProjectMembers($task->project, $all);
        DB::transaction(function () use ($task, $picId, $all, $by) {
            $old = DB::table('task_assignees')->where('task_id', $task->id)->pluck('user_id')->map(fn ($n) => (int) $n)->all();
            DB::table('task_assignees')->where('task_id', $task->id)->whereNotIn('user_id', $all)->delete();
            foreach ($all as $uid) {
                $this->ensureAssignee($task, $uid, $by);
            }
            if ((int) $task->pic_user_id !== $picId) {
                $task->update(['pic_user_id' => $picId]);
            }
            if ($old !== $all) {
                app(TaskWorkflowService::class)->log($task, $by, 'assignees_changed', 'Pelaksana diatur: '.DB::table('users')->whereIn('id', $all)->pluck('name')->implode(', '));
            }
        });
    }
}
