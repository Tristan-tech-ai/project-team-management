<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Petunjuk Teknis 2.3: jangan pernah migrate:fresh / db:wipe pada database bersama yang berisi data.
        DB::prohibitDestructiveCommands();

        Paginator::useBootstrapFive();

        // RBAC dari SQL sumber: super_admin lolos semua (cadangan Gate::before seperti catatan SQL),
        // kemampuan bertitik (projects.view, tasks.review, ...) adalah slug permission dari role_permissions.
        // Kemampuan policy (viewAny, update, ...) tidak bertitik dan diteruskan ke Policy yang juga memeriksa scope.
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability);
            }

            return null;
        });
    }
}
