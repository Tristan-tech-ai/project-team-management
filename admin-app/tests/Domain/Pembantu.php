<?php

namespace Tests\Domain;

use App\Models\Company;
use App\Models\Division;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\User;
use App\Services\ProjectTemplateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Data uji sintetis di dalam transaksi yang dibatalkan (DatabaseTransactions). Role dan izin dari SQL sumber. */
trait Pembantu
{
    protected function company(string $nama = 'Company Uji'): Company
    {
        return Company::create(['code' => 'C-'.Str::upper(Str::random(6)), 'name' => $nama, 'is_active' => 1]);
    }

    protected function divisi(Company $c, string $nama): Division
    {
        return Division::create(['company_id' => $c->id, 'name' => $nama.' '.Str::random(4), 'is_active' => 1]);
    }

    protected function pengguna(string $role, ?Company $c = null, array $divisi = [], bool $aktif = true): User
    {
        $u = User::create(['name' => ucfirst($role).' '.Str::random(5), 'email' => Str::random(10).'@uji.test', 'password' => 'sandi-uji-123456']);
        $sekarang = now();
        DB::table('user_profiles')->insert(['user_id' => $u->id, 'is_active' => $aktif ? 1 : 0, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
        DB::table('user_roles')->insert(['user_id' => $u->id, 'role_id' => DB::table('roles')->where('slug', $role)->value('id'), 'created_at' => $sekarang, 'updated_at' => $sekarang]);
        if ($c) {
            DB::table('company_members')->insert(['company_id' => $c->id, 'user_id' => $u->id, 'is_active' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
        }
        foreach ($divisi as $d) {
            DB::table('division_members')->insert(['division_id' => $d->id, 'user_id' => $u->id, 'is_active' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
        }

        return $u;
    }

    /** Template uji: 3 task utama, task kedua punya 2 subtask. */
    protected function template(string $tipe = 'one_time'): ProjectTemplate
    {
        $t = ProjectTemplate::create(['name' => 'Template Uji '.Str::random(5), 'project_type' => $tipe, 'is_active' => 1]);
        $a = $t->tasks()->create(['title' => 'Analisis', 'task_type' => 'general', 'relative_due_days' => 2, 'sort_order' => 0]);
        $b = $t->tasks()->create(['title' => 'Desain', 'task_type' => 'design', 'relative_due_days' => 5, 'sort_order' => 1]);
        $t->tasks()->create(['parent_id' => $b->id, 'title' => 'Wireframe', 'task_type' => 'general', 'sort_order' => 0]);
        $t->tasks()->create(['parent_id' => $b->id, 'title' => 'Mockup', 'task_type' => 'general', 'sort_order' => 1]);
        $t->tasks()->create(['title' => 'Rilis', 'task_type' => 'general', 'relative_due_days' => 9, 'sort_order' => 2]);

        return $t;
    }

    protected function project(User $oleh, User $pic, Company $c, array $anggota = [], array $divisi = [], string $tipe = 'one_time', ?ProjectTemplate $t = null): Project
    {
        return app(ProjectTemplateService::class)->buatProject([
            'company_id' => $c->id, 'client_id' => null, 'name' => 'Project Uji '.Str::random(4), 'code' => null, 'project_type' => $tipe,
            'pic_user_id' => $pic->id, 'start_at' => now(), 'due_at' => now()->addDays(14), 'project_template_id' => $t?->id,
            'divisions' => array_map(fn ($d) => $d->id, $divisi), 'members' => array_map(fn ($u) => $u->id, $anggota), 'recurrence_unit' => 'month', 'recurrence_interval' => 1,
        ], $oleh);
    }
}
