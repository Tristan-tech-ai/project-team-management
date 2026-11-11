<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\Scope;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        $task->loadMissing('project');

        return $user->hasPermission('tasks.view') && Scope::bisaLihat($user, $task->project);
    }

    /** Ubah isi task: pengelola project atau pelaksana task (PIC/assignee). */
    public function update(User $user, Task $task): bool
    {
        $task->loadMissing('project');

        return $user->hasPermission('tasks.update') && (Scope::pengelola($user, $task->project) || Scope::pelaksana($user, $task));
    }

    public function assign(User $user, Task $task): bool
    {
        $task->loadMissing('project');

        return $user->hasPermission('tasks.assign') && (Scope::pengelola($user, $task->project) || (int) $task->pic_user_id === (int) $user->id);
    }

    public function moveStatus(User $user, Task $task): bool
    {
        $task->loadMissing('project');

        return $user->hasPermission('tasks.move_status') && (Scope::pengelola($user, $task->project) || Scope::pelaksana($user, $task));
    }

    public function submit(User $user, Task $task): bool
    {
        $task->loadMissing('project');

        return $user->hasPermission('tasks.submit') && (Scope::pelaksana($user, $task) || Scope::pengelola($user, $task->project));
    }

    public function review(User $user, Task $task): bool
    {
        return Scope::reviewer($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        $task->loadMissing('project');

        return $user->hasPermission('tasks.delete') && Scope::pengelola($user, $task->project);
    }

    public function approveSchedule(User $user, Task $task): bool
    {
        $task->loadMissing('project');

        return $user->hasPermission('tasks.approve_schedule') && (Scope::pengelola($user, $task->project) || (int) $task->pic_user_id === (int) $user->id);
    }
}
