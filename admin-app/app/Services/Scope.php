<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Data scope (Petunjuk Teknis 3.2 dan 4.2): permission + keanggotaan project.
 * - Super Admin: semua project.
 * - Manager: project di company tempat ia anggota aktif, ditambah project yang ia ikuti.
 * - Member: hanya project tempat ia anggota aktif (project asing tidak terlihat).
 * Pengelola project = Super Admin, Manager pada company project, atau PIC project.
 */
class Scope
{
    public static function projects(User $user): Builder
    {
        $q = Project::query();
        if ($user->isSuperAdmin()) {
            return $q;
        }

        return $q->where(function (Builder $w) use ($user) {
            $w->whereIn('projects.id', DB::table('project_members')->select('project_id')->where('user_id', $user->id)->where('is_active', 1))
                ->orWhere('projects.pic_user_id', $user->id);
            if ($user->hasRole('manager')) {
                $w->orWhereIn('projects.company_id', $user->activeCompanyIds() ?: [0]);
            }
        });
    }

    public static function tasks(User $user): Builder
    {
        $q = Task::query();
        if ($user->isSuperAdmin()) {
            return $q;
        }

        return $q->whereIn('tasks.project_id', self::projects($user)->select('projects.id'));
    }

    public static function bisaLihat(User $user, Project $project): bool
    {
        return $user->isSuperAdmin() || self::projects($user)->whereKey($project->id)->exists();
    }

    /** Pengelola: Super Admin, Manager pada company project, atau PIC project. */
    public static function pengelola(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin() || (int) $project->pic_user_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('manager') && in_array((int) $project->company_id, array_map('intval', $user->activeCompanyIds()), true);
    }

    public static function anggota(User $user, Project $project): bool
    {
        return DB::table('project_members')->where('project_id', $project->id)->where('user_id', $user->id)->where('is_active', 1)->exists();
    }

    public static function pelaksana(User $user, Task $task): bool
    {
        return (int) $task->pic_user_id === (int) $user->id
            || DB::table('task_assignees')->where('task_id', $task->id)->where('user_id', $user->id)->exists();
    }

    /** Reviewer hasil: punya tasks.review dan menjadi pengelola project atau PIC task (bukan pengirim hasil itu sendiri bila ia Member). */
    public static function reviewer(User $user, Task $task): bool
    {
        if (! $user->hasPermission('tasks.review')) {
            return false;
        }
        $task->loadMissing('project');

        return self::pengelola($user, $task->project) || (int) $task->pic_user_id === (int) $user->id;
    }
}
