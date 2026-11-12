<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\MenuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Domain\Pembantu;
use Tests\TestCase;

/** LV-01: login tiap role, menu dinamis sesuai izin, Policy menolak URL langsung, Yajra server-side, Kanban JSON. */
class AdminAksesTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        foreach (['/dashboard', '/projects', '/management', '/master/companies', '/settings/roles'] as $u) {
            $this->get($u)->assertRedirect('/masuk');
        }
    }

    public function test_login_tiap_role_dan_akun_nonaktif_ditolak(): void
    {
        $c = $this->company();
        foreach (['super_admin', 'manager', 'member'] as $r) {
            $u = $this->pengguna($r, $c);
            $this->post('/masuk', ['email' => $u->email, 'password' => 'sandi-uji-123456'])->assertRedirect(route('beranda'));
            $this->assertAuthenticatedAs($u);
            $this->post('/keluar');
        }
        $mati = $this->pengguna('member', $c, [], false);
        $this->post('/masuk', ['email' => $mati->email, 'password' => 'sandi-uji-123456'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_menu_dinamis_sesuai_izin(): void
    {
        $c = $this->company();
        $hitung = function (string $r) use ($c) {
            $m = app(MenuService::class)->untuk($this->pengguna($r, $c));

            return [count($m), array_sum(array_map(fn ($x) => count($x['children']), $m)), array_column($m, 'slug')];
        };
        [$i, $s, $slug] = $hitung('super_admin');
        $this->assertSame([9, 23], [$i, $s]);
        [$i, $s, $slug] = $hitung('manager');
        $this->assertSame([8, 20], [$i, $s]);
        $this->assertNotContains('system-settings', $slug);
        [$i, $s, $slug] = $hitung('member');
        $this->assertSame([6, 10], [$i, $s]);
        $this->assertSame(['dashboard', 'projects', 'tasks', 'calendar', 'activities', 'templates'], $slug);
    }

    public function test_menu_nonaktif_tersembunyi_tetapi_route_tetap_dilindungi_policy(): void
    {
        $c = $this->company();
        $admin = $this->pengguna('super_admin', $c);
        DB::table('menu_items')->where('slug', 'management-reports')->update(['is_active' => 0]);
        $slugAnak = collect(app(MenuService::class)->untuk($admin))->pluck('children')->flatten(1)->pluck('slug');
        $this->assertNotContains('management-reports', $slugAnak);
        $member = $this->pengguna('member', $c);
        $this->actingAs($member)->get('/management/reports')->assertForbidden();
    }

    public function test_member_yang_memaksa_url_admin_mendapat_403(): void
    {
        $c = $this->company();
        $member = $this->pengguna('member', $c);
        $this->actingAs($member);
        foreach (['/management', '/management/projects', '/master/companies', '/master/members', '/settings/roles', '/settings/menus', '/settings/general', '/tasks/review', '/projects/create'] as $u) {
            $this->get($u)->assertForbidden();
        }
        $this->post('/master/companies', ['code' => 'X', 'name' => 'X'])->assertForbidden();
    }

    public function test_manager_lihat_master_data_tapi_tidak_bisa_mengubah(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $this->actingAs($manager)->get('/master/companies')->assertOk();
        $this->post('/master/companies', ['code' => 'BARU', 'name' => 'Baru'])->assertForbidden();
        $this->get('/settings/roles')->assertForbidden();
    }

    public function test_crud_company_oleh_super_admin(): void
    {
        $admin = $this->pengguna('super_admin', $this->company());
        $this->actingAs($admin)->post('/master/companies', ['code' => 'COMP-UJI', 'name' => 'Company Uji Baru', 'is_active' => 1])->assertRedirect('/master/companies');
        $id = DB::table('companies')->where('code', 'COMP-UJI')->value('id');
        $this->assertNotNull($id);
        $this->delete("/master/companies/{$id}")->assertRedirect('/master/companies');
        $this->assertNotNull(DB::table('companies')->where('id', $id)->value('deleted_at'), 'SoftDeletes: baris tetap ada');
    }

    public function test_yajra_server_side_dengan_500_baris_hanya_mengirim_satu_halaman(): void
    {
        $c = $this->company();
        $admin = $this->pengguna('super_admin', $c);
        $p = $this->project($admin, $admin, $c);
        $baris = [];
        for ($i = 1; $i <= 520; $i++) {
            $baris[] = ['project_id' => $p->id, 'pic_user_id' => $admin->id, 'created_by' => $admin->id, 'title' => sprintf('Task beban %03d', $i), 'status' => 'backlog',
                'task_type' => 'general', 'priority' => 'normal', 'created_at' => now(), 'updated_at' => now()];
        }
        foreach (array_chunk($baris, 200) as $b) {
            DB::table('tasks')->insert($b);
        }
        // search.value = pencarian global Yajra (smart: per kata); cari = saringan frasa milik aplikasi.
        $q = http_build_query(['draw' => 3, 'start' => 50, 'length' => 25, 'cari' => 'beban 4', 'search' => ['value' => 'beban', 'regex' => 'false'],
            'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $r = $this->actingAs($admin)->getJson('/projects/'.$p->id.'/tasks/data?'.$q)->assertOk()->json();
        $this->assertSame(3, $r['draw']);
        $this->assertSame(100, $r['recordsTotal'], 'saringan aplikasi diterapkan di server sebelum DataTables');
        $this->assertSame(100, $r['recordsFiltered']);
        $this->assertCount(25, $r['data'], 'hanya satu halaman yang dikirim ke peramban');
        $this->assertSame('Task beban 450', $r['data'][0]['title']);
        // Tanpa saringan: 520 baris, tetap hanya 25 yang dikirim; pencarian global smart per kata.
        $q2 = http_build_query(['draw' => 4, 'start' => 0, 'length' => 25, 'search' => ['value' => 'beban 51', 'regex' => 'false'],
            'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'desc']]]);
        $r2 = $this->getJson('/projects/'.$p->id.'/tasks/data?'.$q2)->assertOk()->json();
        $this->assertSame(520, $r2['recordsTotal']);
        $this->assertSame(15, $r2['recordsFiltered'], 'judul memuat "51": 051, 151, 251, 351, 451, dan 510-519');
        $this->assertCount(15, $r2['data']);
        $this->assertSame('Task beban 519', $r2['data'][0]['title']);
    }

    public function test_status_kanban_ditolak_mengembalikan_json_422_untuk_rollback(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $member = $this->pengguna('member', $c);
        $p = $this->project($manager, $member, $c, [$manager], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->firstOrFail();
        $this->actingAs($member)->patchJson("/tasks/{$t->id}/status", ['status' => 'done'])->assertStatus(422)->assertJsonStructure(['message']);
        $this->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk()->assertJson(['status' => 'in_progress']);
        $this->assertSame('in_progress', $t->refresh()->status);
    }

    public function test_export_csv_menetralkan_rumus(): void
    {
        $c = $this->company();
        $admin = $this->pengguna('super_admin', $c);
        $p = $this->project($admin, $admin, $c);
        $p->update(['name' => '=HYPERLINK("http://contoh.test")']);
        $isi = $this->actingAs($admin)->get('/management/export?jenis=monitoring')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $isi);
        $this->assertStringNotContainsString(';=HYPERLINK', $isi);
        $member = $this->pengguna('member', $c);
        $this->actingAs($member)->get('/management/export?jenis=monitoring')->assertForbidden();
    }

    /** LV-05: tanpa N+1 pada daftar project dan task (jumlah query tidak tumbuh mengikuti jumlah baris). */
    public function test_tanpa_n_plus_1_daftar_project_dan_task(): void
    {
        $c = $this->company();
        $admin = $this->pengguna('super_admin', $c);
        $hitung = function (string $url) use ($admin) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $kolom = str_contains($url, 'tasks') ? 'title' : 'code';
            $r = $this->actingAs($admin)->getJson($url.'?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => 100,
                'columns' => [['data' => $kolom, 'name' => $kolom, 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]))->assertOk();
            $this->assertArrayNotHasKey('error', $r->json(), 'Yajra mengembalikan galat: '.($r->json('error') ?? ''));
            $this->assertNotEmpty($r->json('data'));
            $n = count(DB::getQueryLog());
            if (getenv('LIHAT_QUERY')) { fwrite(STDERR, "
== {$url}
".implode("
", array_map(fn ($q) => substr($q['query'], 0, 160), DB::getQueryLog()))."
"); }
            DB::disableQueryLog();

            return $n;
        };
        $this->project($admin, $admin, $c, [], [], 'one_time', $this->template());
        $hitung('/projects/data'); // pemanasan: cache role dan zona waktu per proses
        $satuProject = $hitung('/projects/data');
        $satuTask = $hitung('/tasks/data');
        for ($i = 0; $i < 29; $i++) {
            $this->project($admin, $admin, $c, [], [], 'one_time', $this->template());
        }
        $this->assertSame($satuProject, $hitung('/projects/data'), 'query daftar project bertambah mengikuti jumlah project (N+1)');
        $this->assertSame($satuTask, $hitung('/tasks/data'), 'query daftar task bertambah mengikuti jumlah task (N+1)');
        $this->assertLessThan(20, $satuProject);
    }

    /** Progress di daftar project dan checklist di daftar task dihitung server (withCount tidak tertimpa select). */
    public function test_progres_di_daftar_yajra_terhitung(): void
    {
        $c = $this->company();
        $admin = $this->pengguna('super_admin', $c);
        $p = $this->project($admin, $admin, $c, [], [], 'one_time', $this->template());
        Task::query()->where('project_id', $p->id)->where('title', 'Analisis')->update(['status' => 'done', 'completed_at' => now()]);
        $desain = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        $desain->subtasks()->orderBy('sort_order')->first()->update(['status' => 'done']);
        $q = http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $qp = http_build_query(['draw' => 1, 'start' => 0, 'length' => 10, 'columns' => [['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
        $proj = collect($this->actingAs($admin)->getJson('/projects/data?'.$qp)->assertOk()->json('data'))->firstWhere('id', $p->id);
        $this->assertSame(33, $proj['progres']);
        $task = collect($this->getJson('/projects/'.$p->id.'/tasks/data?'.$q)->json('data'))->firstWhere('title', 'Desain');
        $this->assertSame(50, $task['subtask']);
    }

    public function test_form_aktivitas_memberi_dasar_geser_dari_deadline_sekarang(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c, [$manager], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->firstOrFail();
        $t->update(['due_at' => now()->addDays(3)]);
        $isi = $this->actingAs($manager)->get("/projects/{$p->id}/activities")->assertOk()->getContent();
        $this->assertStringContainsString('Deadline project sekarang: '.\App\Support\Waktu::tanggalJam($p->due_at), $isi);
        $this->assertStringContainsString('data-dasar-project="'.\App\Support\Waktu::untukInput($p->due_at).'"', $isi);
        $this->assertStringContainsString('&quot;'.$t->id.'&quot;:&quot;'.\App\Support\Waktu::untukInput($t->refresh()->due_at).'&quot;', $isi);
    }
}
