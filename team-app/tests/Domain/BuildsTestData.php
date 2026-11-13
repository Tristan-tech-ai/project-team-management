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

/** Synthetic test data inside a rolled-back transaction (DatabaseTransactions). Roles and permissions come from the source SQL. */
trait BuildsTestData
{
    protected function company(string $name = 'Company Uji'): Company
    {
        return Company::create(['code' => 'C-'.Str::upper(Str::random(6)), 'name' => $name, 'is_active' => 1]);
    }

    protected function division(Company $c, string $name): Division
    {
        return Division::create(['company_id' => $c->id, 'name' => $name.' '.Str::random(4), 'is_active' => 1]);
    }

    protected function user(string $role, ?Company $c = null, array $divisions = [], bool $active = true): User
    {
        $u = User::create(['name' => ucfirst($role).' '.Str::random(5), 'email' => Str::random(10).'@uji.test', 'password' => 'test-password-123456']);
        $now = now();
        DB::table('user_profiles')->insert(['user_id' => $u->id, 'is_active' => $active ? 1 : 0, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('user_roles')->insert(['user_id' => $u->id, 'role_id' => DB::table('roles')->where('slug', $role)->value('id'), 'created_at' => $now, 'updated_at' => $now]);
        if ($c) {
            DB::table('company_members')->insert(['company_id' => $c->id, 'user_id' => $u->id, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach ($divisions as $d) {
            DB::table('division_members')->insert(['division_id' => $d->id, 'user_id' => $u->id, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $u;
    }

    /** Test template: 3 main tasks, the second task has 2 subtasks. */
    protected function template(string $type = 'one_time'): ProjectTemplate
    {
        $t = ProjectTemplate::create(['name' => 'Template Uji '.Str::random(5), 'project_type' => $type, 'is_active' => 1]);
        $a = $t->tasks()->create(['title' => 'Analisis', 'task_type' => 'general', 'relative_due_days' => 2, 'sort_order' => 0]);
        $b = $t->tasks()->create(['title' => 'Desain', 'task_type' => 'design', 'relative_due_days' => 5, 'sort_order' => 1]);
        $t->tasks()->create(['parent_id' => $b->id, 'title' => 'Wireframe', 'task_type' => 'general', 'sort_order' => 0]);
        $t->tasks()->create(['parent_id' => $b->id, 'title' => 'Mockup', 'task_type' => 'general', 'sort_order' => 1]);
        $t->tasks()->create(['title' => 'Rilis', 'task_type' => 'general', 'relative_due_days' => 9, 'sort_order' => 2]);

        return $t;
    }

    protected function project(User $by, User $pic, Company $c, array $members = [], array $divisions = [], string $type = 'one_time', ?ProjectTemplate $t = null): Project
    {
        return app(ProjectTemplateService::class)->createProject([
            'company_id' => $c->id, 'client_id' => null, 'name' => 'Project Uji '.Str::random(4), 'code' => null, 'project_type' => $type,
            'pic_user_id' => $pic->id, 'start_at' => now(), 'due_at' => now()->addDays(14), 'project_template_id' => $t?->id,
            'divisions' => array_map(fn ($d) => $d->id, $divisions), 'members' => array_map(fn ($u) => $u->id, $members), 'recurrence_unit' => 'month', 'recurrence_interval' => 1,
        ], $by);
    }
}
