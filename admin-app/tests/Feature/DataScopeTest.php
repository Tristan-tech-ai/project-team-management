<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Division;
use App\Models\Position;
use App\Models\Task;
use App\Services\ProjectTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/**
 * Supervisor revision: Company, Client, PIC, member filters and master data only show data within the user's scope
 * (Technical Guide 3.2: Manager = companies where they are an active member; Member = their member projects).
 */
class DataScopeTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    /** Two separate companies, each with a client, PIC, division, position, project, and assignee. */
    private function twoCompanies(): array
    {
        $result = [];
        foreach (['A', 'B'] as $k) {
            $c = $this->company("Company Cakupan {$k} ".Str::random(4));
            $manager = $this->user('manager', $c);
            $pic = $this->user('member', $c);
            $assignee = $this->user('member', $c);
            $client = Client::create(['code' => 'CL-'.Str::upper(Str::random(6)), 'name' => "Client Cakupan {$k} ".Str::random(4), 'is_active' => 1]);
            $divisions = $this->division($c, "Divisi Cakupan {$k}");
            $positions = Position::create(['company_id' => $c->id, 'name' => "Jabatan Cakupan {$k} ".Str::random(4), 'is_active' => 1]);
            $p = $this->project($manager, $pic, $c, [$pic, $assignee], [], 'one_time', $this->template());
            $p->update(['client_id' => $client->id]);
            $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
            app(ProjectTeamService::class)->setAssignees($t, $assignee->id, [$assignee->id], $manager);
            $result[$k] = compact('c', 'manager', 'pic', 'assignee', 'client', 'divisions', 'positions', 'p');
        }

        return [$result['A'], $result['B']];
    }

    private function escapedName($m): string
    {
        return e($m->name);
    }

    public function test_project_management_and_task_filters_stay_in_scope(): void
    {
        [$a, $b] = $this->twoCompanies();
        $m = $a['manager'];

        $html = $this->actingAs($m)->get('/projects')->assertOk()->getContent();
        foreach ([$a['c'], $a['client'], $a['pic']] as $x) {
            $this->assertStringContainsString($this->escapedName($x), $html);
        }
        foreach ([$b['c'], $b['client'], $b['pic']] as $x) {
            $this->assertStringNotContainsString($this->escapedName($x), $html);
        }

        $html = $this->actingAs($m)->get('/management/projects')->assertOk()->getContent();
        $this->assertStringContainsString($this->escapedName($a['c']), $html);
        $this->assertStringNotContainsString($this->escapedName($b['c']), $html);
        $this->assertStringNotContainsString($this->escapedName($b['client']), $html);

        $html = $this->actingAs($m)->get('/management/teams')->assertOk()->getContent();
        $this->assertStringContainsString($this->escapedName($a['divisions']), $html);
        $this->assertStringNotContainsString($this->escapedName($b['divisions']), $html);

        $html = $this->actingAs($m)->get('/tasks')->assertOk()->getContent();
        $this->assertStringContainsString($this->escapedName($a['assignee']), $html);
        $this->assertStringNotContainsString($this->escapedName($b['assignee']), $html);

        // A Member only sees options from projects they joined.
        $html = $this->actingAs($a['assignee'])->get('/projects')->assertOk()->getContent();
        $this->assertStringContainsString($this->escapedName($a['client']), $html);
        $this->assertStringNotContainsString($this->escapedName($b['client']), $html);
        $this->assertStringNotContainsString($this->escapedName($b['c']), $html);

        // Super Admin sees everything.
        $html = $this->actingAs($this->user('super_admin', $a['c']))->get('/projects')->assertOk()->getContent();
        $this->assertStringContainsString($this->escapedName($b['client']), $html);
        $this->assertStringContainsString($this->escapedName($b['pic']), $html);
    }

    public function test_manager_master_data_is_limited_to_own_companies(): void
    {
        [$a, $b] = $this->twoCompanies();
        $m = $a['manager'];
        $fetch = function (string $url, string $columns) use ($m): array {
            $q = http_build_query(['draw' => 1, 'start' => 0, 'length' => 500,
                'columns' => [['data' => $columns, 'name' => $columns, 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
            $r = $this->actingAs($m)->getJson($url.'?'.$q)->assertOk();
            $this->assertArrayNotHasKey('error', $r->json(), (string) $r->json('error'));

            return array_column($r->json('data'), $columns);
        };

        $this->assertContains($a['c']->name, $fetch('/master/companies/data', 'name'));
        $this->assertNotContains($b['c']->name, $fetch('/master/companies/data', 'name'));
        $this->assertContains($a['client']->name, $fetch('/master/clients/data', 'name'));
        $this->assertNotContains($b['client']->name, $fetch('/master/clients/data', 'name'));
        $this->assertContains($a['divisions']->name, $fetch('/master/divisions/data', 'name'));
        $this->assertNotContains($b['divisions']->name, $fetch('/master/divisions/data', 'name'));
        $this->assertContains($a['positions']->name, $fetch('/master/positions/data', 'name'));
        $this->assertNotContains($b['positions']->name, $fetch('/master/positions/data', 'name'));
        $members = $fetch('/master/members/data', 'email');
        $this->assertContains($a['assignee']->email, $members);
        $this->assertNotContains($b['assignee']->email, $members);
    }

    public function test_master_changes_outside_scope_are_refused_even_with_manage_permission(): void
    {
        [$a, $b] = $this->twoCompanies();
        $m = $a['manager'];
        // The Super Admin can give the manager role manage permissions; the company scope still applies.
        $role = DB::table('roles')->where('slug', 'manager')->value('id');
        foreach (['companies.manage', 'divisions.manage', 'positions.manage', 'members.manage', 'clients.manage'] as $slug) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        }
        $m->forgetPermissions();

        $this->actingAs($m)->get("/master/companies/{$a['c']->id}/edit")->assertOk();
        $this->actingAs($m)->get("/master/companies/{$b['c']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/divisions/{$a['divisions']->id}/edit")->assertOk();
        $this->actingAs($m)->get("/master/divisions/{$b['divisions']->id}/edit")->assertForbidden();
        $this->actingAs($m)->patch("/master/divisions/{$b['divisions']->id}/toggle")->assertForbidden();
        $this->actingAs($m)->get("/master/positions/{$b['positions']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/members/{$b['assignee']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/clients/{$b['client']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/clients/{$a['client']->id}/edit")->assertOk();

        $form = $this->actingAs($m)->get('/master/divisions/create')->assertOk()->getContent();
        $this->assertStringContainsString($this->escapedName($a['c']), $form);
        $this->assertStringNotContainsString($this->escapedName($b['c']), $form);
        $this->assertStringNotContainsString($this->escapedName($b['pic']), $form);
        $this->actingAs($m)->post('/master/divisions', ['company_id' => $b['c']->id, 'name' => 'Divisi Selundupan', 'is_active' => 1])->assertSessionHasErrors('company_id');
        $this->assertFalse(Division::query()->where('name', 'Divisi Selundupan')->exists());

        $this->actingAs($m)->post('/master/members', ['name' => 'Calon Admin', 'email' => Str::random(8).'@uji.test', 'password' => 'test-password-123456',
            'role' => 'super_admin', 'companies' => [$a['c']->id], 'is_active' => 1])->assertSessionHasErrors('role');
        $this->actingAs($m)->post('/master/members', ['name' => 'Anggota Luar', 'email' => Str::random(8).'@uji.test', 'password' => 'test-password-123456',
            'role' => 'member', 'companies' => [$b['c']->id], 'is_active' => 1])->assertSessionHasErrors('companies.0');
    }
}
