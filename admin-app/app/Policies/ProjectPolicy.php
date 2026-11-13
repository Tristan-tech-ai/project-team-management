<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\Scope;

/** Project policy: permission (role_permissions) AND membership scope, checked on the server on every request. */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.view') && Scope::canView($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.update') && Scope::isManager($user, $project);
    }

    public function manageTeam(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.manage_team') && Scope::isManager($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.delete') && Scope::isManager($user, $project);
    }

    /** Creating a task in a project: a project manager, or an active project member with tasks.create (Member "own project tasks"). */
    public function createTask(User $user, Project $project): bool
    {
        return $user->hasPermission('tasks.create') && (Scope::isManager($user, $project) || Scope::isProjectMember($user, $project));
    }

    public function createActivity(User $user, Project $project): bool
    {
        return $user->hasPermission('activities.create') && (Scope::isManager($user, $project) || Scope::isProjectMember($user, $project));
    }

    public function approveSchedule(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.approve_schedule') && Scope::isManager($user, $project);
    }
}
