<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Division;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data scope (Technical Guide 3.2 and 4.2): permission + project membership.
 * - Super Admin: every project.
 * - Manager: projects in companies where they are an active member, plus projects they joined.
 * - Member: only projects where they are an active member (foreign projects are invisible).
 * Project manager = Super Admin, a Manager of the project company, or the project PIC.
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

    /**
     * Companies where the user is an active member; null means every company (Super Admin).
     * Used for master data (companies, divisions, positions, members) as in Technical Guide 3.2.
     */
    public static function companyIds(User $user): ?array
    {
        return $user->isSuperAdmin() ? null : array_values(array_map('intval', $user->activeCompanyIds()));
    }

    public static function canAccessCompany(User $user, mixed $companyId): bool
    {
        $ids = self::companyIds($user);

        return $ids === null || in_array((int) $companyId, $ids, true);
    }

    /** Validation rule: the selected company must be within the user's scope. */
    public static function companyRule(User $user): \Closure
    {
        return function (string $attribute, mixed $values, \Closure $failed) use ($user): void {
            if (! self::canAccessCompany($user, $values)) {
                $failed('Company ini di luar cakupan aksesmu.');
            }
        };
    }

    /** Limits a query with a company_id column to the user's companies (no limit for the Super Admin). */
    public static function limitToCompanies(Builder $q, User $user, string $columns = 'company_id'): Builder
    {
        $ids = self::companyIds($user);

        return $ids === null ? $q : $q->whereIn($columns, $ids ?: [0]);
    }

    /** Members (users) visible in master data: active members of the user's companies, or everyone for the Super Admin. */
    public static function masterMembers(User $user): Builder
    {
        $q = User::query();
        $ids = self::companyIds($user);

        return $ids === null ? $q : $q->whereIn('users.id', DB::table('company_members')->select('user_id')->whereIn('company_id', $ids ?: [0])->where('is_active', 1));
    }

    /** Visible clients: all for the Super Admin, otherwise clients of projects within scope. */
    public static function clients(User $user): Builder
    {
        $q = Client::query();

        return $user->isSuperAdmin() ? $q : $q->whereIn('clients.id', self::projects($user)->whereNotNull('projects.client_id')->select('projects.client_id'));
    }

    /** Company filter options: membership companies plus companies of projects within scope. */
    public static function filterCompanies(User $user): Collection
    {
        $q = Company::query();
        if (! $user->isSuperAdmin()) {
            $q->where(fn (Builder $w) => $w->whereIn('companies.id', $user->activeCompanyIds() ?: [0])
                ->orWhereIn('companies.id', self::projects($user)->select('projects.company_id')));
        }

        return $q->orderBy('name')->pluck('name', 'id');
    }

    public static function filterClients(User $user): Collection
    {
        return self::clients($user)->orderBy('name')->pluck('name', 'id');
    }

    /** PIC filter options: only PICs of projects within scope. */
    public static function filterPics(User $user): Collection
    {
        return User::query()->whereIn('users.id', self::projects($user)->whereNotNull('projects.pic_user_id')->select('projects.pic_user_id'))
            ->orderBy('name')->pluck('name', 'id');
    }

    /** Assignee filter options: only assignees of tasks within scope. */
    public static function filterAssignees(User $user): Collection
    {
        return User::query()->whereIn('users.id', DB::table('task_assignees')->select('user_id')->whereIn('task_id', self::tasks($user)->select('tasks.id')))
            ->orderBy('name')->pluck('name', 'id');
    }

    /** Division filter options: active divisions in companies visible to the user. */
    public static function filterDivisions(User $user): Collection
    {
        $q = Division::query()->where('is_active', 1);
        if (! $user->isSuperAdmin()) {
            $q->whereIn('company_id', self::filterCompanies($user)->keys()->all() ?: [0]);
        }

        return $q->orderBy('name')->pluck('name', 'id');
    }

    /**
     * Team App (Technical Guide 5.2, "My Projects: member projects only"): projects where the user is an active member
     * or the PIC. Used for the list, detail, Kanban, quick add, activities, and review in the Team App; the Super Admin sees all.
     * Managers see every project of their company in the Admin App (Scope::projects).
     */
    public static function memberProjects(User $user): Builder
    {
        $q = Project::query();
        if ($user->isSuperAdmin()) {
            return $q;
        }

        return $q->where(fn (Builder $w) => $w->whereIn('projects.id', DB::table('project_members')->select('project_id')->where('user_id', $user->id)->where('is_active', 1))
            ->orWhere('projects.pic_user_id', $user->id));
    }

    public static function isMemberOrPic(User $user, Project $project): bool
    {
        return $user->isSuperAdmin() || (int) $project->pic_user_id === (int) $user->id || self::isProjectMember($user, $project);
    }

    public static function canView(User $user, Project $project): bool
    {
        return $user->isSuperAdmin() || self::projects($user)->whereKey($project->id)->exists();
    }

    /** Project manager: Super Admin, a Manager of the project company, or the project PIC. */
    public static function isManager(User $user, Project $project): bool
    {
        if ($user->isSuperAdmin() || (int) $project->pic_user_id === (int) $user->id) {
            return true;
        }

        return $user->hasRole('manager') && in_array((int) $project->company_id, array_map('intval', $user->activeCompanyIds()), true);
    }

    public static function isProjectMember(User $user, Project $project): bool
    {
        return DB::table('project_members')->where('project_id', $project->id)->where('user_id', $user->id)->where('is_active', 1)->exists();
    }

    public static function isAssignee(User $user, Task $task): bool
    {
        return (int) $task->pic_user_id === (int) $user->id
            || DB::table('task_assignees')->where('task_id', $task->id)->where('user_id', $user->id)->exists();
    }

    /** Result reviewer: has tasks.review and manages the project or is the task PIC (never the Member who sent the result). */
    public static function reviewer(User $user, Task $task): bool
    {
        if (! $user->hasPermission('tasks.review')) {
            return false;
        }
        $task->loadMissing('project');

        return self::isManager($user, $task->project) || (int) $task->pic_user_id === (int) $user->id;
    }
}
