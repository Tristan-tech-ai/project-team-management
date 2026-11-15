<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Active members of several projects in one query, for the one-screen Add task form (U9). */
final class ProjectPeople
{
    /** @return array<int, list<array{id:int, name:string}>> project id => members sorted by name */
    public static function byProject(iterable $projectIds): array
    {
        $ids = collect($projectIds)->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        $rows = DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')
            ->whereIn('project_members.project_id', $ids)->where('project_members.is_active', 1)->orderBy('users.name')
            ->get(['project_members.project_id', 'users.id', 'users.name']);
        $load = self::openTasks($rows->pluck('id')->unique()->all());

        return $rows->groupBy('project_id')->map(fn ($group) => $group->map(fn ($r) => ['id' => (int) $r->id, 'name' => $r->name, 'open' => $load[(int) $r->id] ?? 0])->values()->all())->all();
    }

    /**
     * I5 workload: main tasks not yet done that each person holds as PIC or assignee, in one query, so work is shared fairly.
     *
     * @return array<int, int> user id => open task count
     */
    public static function openTasks(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $holders = DB::table('tasks')->whereNull('tasks.parent_id')->whereNull('tasks.deleted_at')->where('tasks.status', '!=', 'done')->whereIn('tasks.pic_user_id', $userIds)
            ->select('tasks.id', 'tasks.pic_user_id as user_id')
            ->union(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->whereNull('tasks.parent_id')->whereNull('tasks.deleted_at')
                ->where('tasks.status', '!=', 'done')->whereIn('task_assignees.user_id', $userIds)->select('tasks.id', 'task_assignees.user_id'));

        return DB::query()->fromSub($holders, 'h')->selectRaw('user_id, count(distinct id) as n')->groupBy('user_id')->pluck('n', 'user_id')
            ->map(fn ($n) => (int) $n)->all();
    }
}
