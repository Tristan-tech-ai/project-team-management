<?php

namespace App\Support;

use App\Models\User;

/**
 * Demo accounts for a local installation, so a reviewer can try every role right after setup.
 * The quick sign-in buttons only fill the normal login form (authentication is never skipped) and only appear when
 * APP_ENV=local and PTM_DEMO_LOGIN=true. Seeders refuse DEFAULT_PASSWORD outside the local environment.
 */
final class DemoAccounts
{
    /** Documented password for local demo accounts when the .env leaves it empty (at least 12 characters, as the seeders require). */
    public const DEFAULT_PASSWORD = 'ptm-demo-2026';

    public const MANAGER_EMAIL = 'demo.manager@example.test';

    public const MEMBER_EMAIL = 'demo.member@example.test';

    public static function enabled(): bool
    {
        return app()->environment('local') && (bool) config('app.demo_login');
    }

    /**
     * Accounts offered on the login page of this app; only accounts that exist and are active are listed.
     *
     * @return list<array{role: string, name: string, email: string, password: string}>
     */
    public static function forLogin(bool $withSuperAdmin): array
    {
        if (! self::enabled()) {
            return [];
        }
        $candidates = [];
        if ($withSuperAdmin && config('app.initial_admin_email') && config('app.initial_admin_password')) {
            $candidates[] = ['Super Admin', (string) config('app.initial_admin_email'), (string) config('app.initial_admin_password')];
        }
        if (config('app.demo_password')) {
            $candidates[] = ['Manager', self::MANAGER_EMAIL, (string) config('app.demo_password')];
            $candidates[] = ['Member', self::MEMBER_EMAIL, (string) config('app.demo_password')];
        }
        $users = User::query()->whereIn('email', array_column($candidates, 1))->get(['id', 'name', 'email'])->keyBy('email');

        $result = [];
        foreach ($candidates as [$role, $email, $password]) {
            $user = $users->get($email);
            if ($user && $user->isActive()) {
                $result[] = ['role' => $role, 'name' => $user->name, 'email' => $email, 'password' => $password];
            }
        }

        return $result;
    }
}
