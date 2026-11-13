<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** LV-01: login per role, dynamic menu by permission, Policy refuses direct URLs, Yajra server-side, Kanban JSON. */
class AdminAccessTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_guest_is_redirected_to_login(): void
    {
        foreach (['/dashboard', '/projects', '/management', '/master/companies', '/settings/roles'] as $u) {
            $this->get($u)->assertRedirect('/login');
        }
    }

    public function test_each_role_can_log_in_and_inactive_accounts_are_rejected(): void
    {
        $c = $this->company();
        foreach (['super_admin', 'manager', 'member'] as $r) {
            $u = $this->user($r, $c);
            $this->post('/login', ['email' => $u->email, 'password' => 'test-password-123456'])->assertRedirect(route('home'));
            $this->assertAuthenticatedAs($u);
            $this->post('/logout');
        }
        $inactive = $this->user('member', $c, [], false);
        $this->post('/login', ['email' => $inactive->email, 'password' => 'test-password-123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dynamic_menu_follows_permissions(): void
    {
        $c = $this->company();
        $counts = function (string $r) use ($c) {
            $m = app(MenuService::class)->for($this->user($r, $c));

            return [count($m), array_sum(array_map(fn ($x) => count($x['children']), $m)), array_column($m, 'slug')];
        };
        [$i, $s, $slug] = $counts('super_admin');
        $this->assertSame([9, 23], [$i, $s]);
        [$i, $s, $slug] = $counts('manager');
        $this->assertSame([8, 20], [$i, $s]);
        $this->assertNotContains('system-settings', $slug);
        [$i, $s, $slug] = $counts('member');
        $this->assertSame([6, 10], [$i, $s]);
        $this->assertSame(['dashboard', 'projects', 'tasks', 'calendar', 'activities', 'templates'], $slug);
    }

    public function test_inactive_menu_is_hidden_but_route_stays_protected_by_policy(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        DB::table('menu_items')->where('slug', 'management-reports')->update(['is_active' => 0]);
        $childSlug = collect(app(MenuService::class)->for($admin))->pluck('children')->flatten(1)->pluck('slug');
        $this->assertNotContains('management-reports', $childSlug);
        $member = $this->user('member', $c);
        $this->actingAs($member)->get('/management/reports')->assertForbidden();
    }

    public function test_member_forcing_admin_urls_gets_403(): void
    {
        $c = $this->company();
        $member = $this->user('member', $c);
        $this->actingAs($member);
        foreach (['/management', '/management/projects', '/master/companies', '/master/members', '/settings/roles', '/settings/menus', '/settings/general', '/tasks/review', '/projects/create'] as $u) {
            $this->get($u)->assertForbidden();
        }
        $this->post('/master/companies', ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    public function test_manager_can_view_but_not_change_master_data(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $this->actingAs($manager)->get('/master/companies')->assertOk();
        $this->post('/master/companies', ['code' => 'BARU', 'name' => 'Baru'])->assertForbidden();
        $this->get('/settings/roles')->assertForbidden();
    }

    public function test_super_admin_can_crud_companies(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $this->actingAs($admin)->post('/master/companies', ['code' => 'COMP-UJI', 'name' => 'Company Uji Baru', 'is_active' => 1])->assertRedirect('/master/companies');
        $id = DB::table('companies')->where('code', 'COMP-UJI')->value('id');
        $this->assertNotNull($id);
        $this->delete("/master/companies/{$id}")->assertRedirect('/master/companies');
        $this->assertNotNull(DB::table('companies')->where('id', $id)->value('deleted_at'), 'SoftDeletes: the row stays');
    }

    public function test_yajra_server_side_with_500_rows_sends_one_page_only(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $p = $this->project($admin, $admin, $c);
        $rows = [];
        for ($i = 1; $i <= 520; $i++) {
            $rows[] = ['project_id' => $p->id, 'pic_user_id' => $admin->id, 'created_by' => $admin->id, 'title' => sprintf('Task beban %03d', $i), 'status' => 'backlog',
                'task_type' => 'general', 'priority' => 'normal', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($rows, 200) as $b) {
            DB::table('tasks')->insert($b);
        }
        // search.value = Yajra global search (smart: per word); keyword = the app's own phrase filter.
        $q = http_build_query(['draw' => 3, 'start' => 50, 'length' => 25, 'keyword' => 'beban 4', 'search' => ['value' => 'beban', 'regex' => 'false'],
            'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $r = $this->actingAs($admin)->getJson('/projects/'.$p->id.'/tasks/data?'.$q)->assertOk()->json();
        $this->assertSame(3, $r['draw']);
        $this->assertSame(100, $r['recordsTotal'], 'saringan aplikasi diterapkan di server sebelum DataTables');
        $this->assertSame(100, $r['recordsFiltered']);
        $this->assertCount(25, $r['data'], 'only one page is sent to the browser');
        $this->assertSame('Task beban 450', $r['data'][0]['title']);
        // Without filters: 520 rows, still only 25 are sent; global search is smart per word.
        $q2 = http_build_query(['draw' => 4, 'start' => 0, 'length' => 25, 'search' => ['value' => 'beban 51', 'regex' => 'false'],
            'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'desc']]]);
        $r2 = $this->getJson('/projects/'.$p->id.'/tasks/data?'.$q2)->assertOk()->json();
        $this->assertSame(520, $r2['recordsTotal']);
        $this->assertSame(15, $r2['recordsFiltered'], 'titles containing "51": 051, 151, 251, 351, 451, and 510-519');
        $this->assertCount(15, $r2['data']);
        $this->assertSame('Task beban 519', $r2['data'][0]['title']);
    }

    public function test_rejected_kanban_status_returns_422_json_for_rollback(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $p = $this->project($manager, $member, $c, [$manager], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->firstOrFail();
        $this->actingAs($member)->patchJson("/tasks/{$t->id}/status", ['status' => 'done'])->assertStatus(422)->assertJsonStructure(['message']);
        $this->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk()->assertJson(['status' => 'in_progress']);
        $this->assertSame('in_progress', $t->refresh()->status);
    }

    public function test_csv_export_neutralizes_formulas(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $p = $this->project($admin, $admin, $c);
        $p->update(['name' => '=HYPERLINK("http://contoh.test")']);
        $content = $this->actingAs($admin)->get('/management/export?kind=monitoring')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringNotContainsString(';=HYPERLINK', $content);
        $member = $this->user('member', $c);
        $this->actingAs($member)->get('/management/export?kind=monitoring')->assertForbidden();
    }

    /** LV-05: no N+1 on the project and task lists (the number of queries does not grow with the number of rows). */
    public function test_project_and_task_lists_have_no_n_plus_one(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $counts = function (string $url) use ($admin) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $columns = str_contains($url, 'tasks') ? 'title' : 'code';
            $r = $this->actingAs($admin)->getJson($url.'?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => 100,
                'columns' => [['data' => $columns, 'name' => $columns, 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]))->assertOk();
            $this->assertArrayNotHasKey('error', $r->json(), 'Yajra mengembalikan galat: '.($r->json('error') ?? ''));
            $this->assertNotEmpty($r->json('data'));
            $n = count(DB::getQueryLog());
            if (getenv('SHOW_QUERIES')) { fwrite(STDERR, "
== {$url}
".implode("
", array_map(fn ($q) => substr($q['query'], 0, 160), DB::getQueryLog()))."
"); }
            DB::disableQueryLog();

            return $n;
        };
        $this->project($admin, $admin, $c, [], [], 'one_time', $this->template());
        $counts('/projects/data'); // warm-up: role and time zone caches per process
        $oneProject = $counts('/projects/data');
        $oneTask = $counts('/tasks/data');
        for ($i = 0; $i < 29; $i++) {
            $this->project($admin, $admin, $c, [], [], 'one_time', $this->template());
        }
        $this->assertSame($oneProject, $counts('/projects/data'), 'query daftar project bertambah mengikuti jumlah project (N+1)');
        $this->assertSame($oneTask, $counts('/tasks/data'), 'query daftar task bertambah mengikuti jumlah task (N+1)');
        $this->assertLessThan(20, $oneProject);
    }

    /** Progress in the project list and checklists in the task list are computed on the server (withCount is not overwritten by select). */
    public function test_progress_is_calculated_in_yajra_list(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $p = $this->project($admin, $admin, $c, [], [], 'one_time', $this->template());
        Task::query()->where('project_id', $p->id)->where('title', 'Analisis')->update(['status' => 'done', 'completed_at' => now()]);
        $design = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        $design->subtasks()->orderBy('sort_order')->first()->update(['status' => 'done']);
        $q = http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $qp = http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'columns' => [['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $proj = collect($this->actingAs($admin)->getJson('/projects/data?'.$qp)->assertOk()->json('data'))->firstWhere('id', $p->id);
        $this->assertSame(33, $proj['progress']);
        $task = collect($this->getJson('/projects/'.$p->id.'/tasks/data?'.$q)->json('data'))->firstWhere('title', 'Desain');
        $this->assertSame(50, $task['subtask']);
    }

    public function test_activity_form_shifts_deadline_from_current_due_date(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c, [$manager], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->firstOrFail();
        $t->update(['due_at' => now()->addDays(3)]);
        $content = $this->actingAs($manager)->get("/projects/{$p->id}/activities")->assertOk()->getContent();
        $this->assertStringContainsString('Deadline project sekarang: '.\App\Support\Dates::dateTime($p->due_at), $content);
        $this->assertStringContainsString('data-base-project="'.\App\Support\Dates::forInput($p->due_at).'"', $content);
        $this->assertStringContainsString('&quot;'.$t->id.'&quot;:&quot;'.\App\Support\Dates::forInput($t->refresh()->due_at).'&quot;', $content);
    }
}
