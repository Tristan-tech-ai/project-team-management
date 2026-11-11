<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\Scope;

/** Policy project: permission (role_permissions) DAN scope keanggotaan, diperiksa di server setiap request. */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.view') && Scope::bisaLihat($user, $project);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('projects.create');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.update') && Scope::pengelola($user, $project);
    }

    public function manageTeam(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.manage_team') && Scope::pengelola($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.delete') && Scope::pengelola($user, $project);
    }

    /** Membuat task di project: pengelola, atau anggota aktif project yang punya tasks.create (Member "task project sendiri"). */
    public function createTask(User $user, Project $project): bool
    {
        return $user->hasPermission('tasks.create') && (Scope::pengelola($user, $project) || Scope::anggota($user, $project));
    }

    public function createActivity(User $user, Project $project): bool
    {
        return $user->hasPermission('activities.create') && (Scope::pengelola($user, $project) || Scope::anggota($user, $project));
    }

    public function approveSchedule(User $user, Project $project): bool
    {
        return $user->hasPermission('projects.approve_schedule') && Scope::pengelola($user, $project);
    }
}
