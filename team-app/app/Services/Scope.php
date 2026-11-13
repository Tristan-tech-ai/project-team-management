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

    /**
     * Company tempat pengguna menjadi anggota aktif; null berarti semua company (Super Admin).
     * Dipakai untuk data master (company, divisi, jabatan, anggota) sesuai Petunjuk Teknis 3.2.
     */
    public static function companyIds(User $user): ?array
    {
        return $user->isSuperAdmin() ? null : array_values(array_map('intval', $user->activeCompanyIds()));
    }

    public static function bolehCompany(User $user, mixed $companyId): bool
    {
        $ids = self::companyIds($user);

        return $ids === null || in_array((int) $companyId, $ids, true);
    }

    /** Aturan validasi: company yang dipilih harus dalam cakupan pengguna. */
    public static function aturanCompany(User $user): \Closure
    {
        return function (string $atribut, mixed $nilai, \Closure $gagal) use ($user): void {
            if (! self::bolehCompany($user, $nilai)) {
                $gagal('Company ini di luar cakupan aksesmu.');
            }
        };
    }

    /** Batasi query berkolom company_id ke company pengguna (tanpa batas untuk Super Admin). */
    public static function batasiCompany(Builder $q, User $user, string $kolom = 'company_id'): Builder
    {
        $ids = self::companyIds($user);

        return $ids === null ? $q : $q->whereIn($kolom, $ids ?: [0]);
    }

    /** Anggota (users) yang boleh terlihat di data master: anggota aktif company pengguna, atau semua untuk Super Admin. */
    public static function anggotaMaster(User $user): Builder
    {
        $q = User::query();
        $ids = self::companyIds($user);

        return $ids === null ? $q : $q->whereIn('users.id', DB::table('company_members')->select('user_id')->whereIn('company_id', $ids ?: [0])->where('is_active', 1));
    }

    /** Client yang boleh terlihat: semua untuk Super Admin, selain itu client dari project dalam cakupan. */
    public static function clients(User $user): Builder
    {
        $q = Client::query();

        return $user->isSuperAdmin() ? $q : $q->whereIn('clients.id', self::projects($user)->whereNotNull('projects.client_id')->select('projects.client_id'));
    }

    /** Pilihan filter Company: company keanggotaan ditambah company dari project dalam cakupan. */
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

    /** Pilihan filter PIC: hanya PIC dari project dalam cakupan. */
    public static function filterPics(User $user): Collection
    {
        return User::query()->whereIn('users.id', self::projects($user)->whereNotNull('projects.pic_user_id')->select('projects.pic_user_id'))
            ->orderBy('name')->pluck('name', 'id');
    }

    /** Pilihan filter pelaksana: hanya pelaksana task dalam cakupan. */
    public static function filterPelaksana(User $user): Collection
    {
        return User::query()->whereIn('users.id', DB::table('task_assignees')->select('user_id')->whereIn('task_id', self::tasks($user)->select('tasks.id')))
            ->orderBy('name')->pluck('name', 'id');
    }

    /** Pilihan filter divisi: divisi aktif di company yang terlihat oleh pengguna. */
    public static function filterDivisi(User $user): Collection
    {
        $q = Division::query()->where('is_active', 1);
        if (! $user->isSuperAdmin()) {
            $q->whereIn('company_id', self::filterCompanies($user)->keys()->all() ?: [0]);
        }

        return $q->orderBy('name')->pluck('name', 'id');
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
