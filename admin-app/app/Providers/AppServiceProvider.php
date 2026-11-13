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
        // Technical Guide 2.3: never run migrate:fresh / db:wipe on the shared database that holds data.
        DB::prohibitDestructiveCommands();

        Paginator::useBootstrapFive();

        // RBAC from the source SQL: super_admin passes everything (Gate::before fallback, as the SQL notes say),
        // dotted abilities (projects.view, tasks.review, ...) are permission slugs from role_permissions.
        // Policy abilities (viewAny, update, ...) have no dot and go to the Policy, which also checks the scope.
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
