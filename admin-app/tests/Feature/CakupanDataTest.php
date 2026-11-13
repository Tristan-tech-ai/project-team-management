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
use Tests\Domain\Pembantu;
use Tests\TestCase;

/**
 * Revisi pembina: filter Company, Client, PIC, anggota, dan data master hanya menampilkan data dalam cakupan pengguna
 * (Petunjuk Teknis 3.2: Manager = company tempat ia anggota aktif; Member = project keanggotaannya).
 */
class CakupanDataTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    /** Dua company terpisah, masing-masing dengan client, PIC, divisi, jabatan, project, dan pelaksana. */
    private function duaCompany(): array
    {
        $hasil = [];
        foreach (['A', 'B'] as $k) {
            $c = $this->company("Company Cakupan {$k} ".Str::random(4));
            $manager = $this->pengguna('manager', $c);
            $pic = $this->pengguna('member', $c);
            $pelaksana = $this->pengguna('member', $c);
            $client = Client::create(['code' => 'CL-'.Str::upper(Str::random(6)), 'name' => "Client Cakupan {$k} ".Str::random(4), 'is_active' => 1]);
            $divisi = $this->divisi($c, "Divisi Cakupan {$k}");
            $jabatan = Position::create(['company_id' => $c->id, 'name' => "Jabatan Cakupan {$k} ".Str::random(4), 'is_active' => 1]);
            $p = $this->project($manager, $pic, $c, [$pic, $pelaksana], [], 'one_time', $this->template());
            $p->update(['client_id' => $client->id]);
            $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
            app(ProjectTeamService::class)->aturAssignee($t, $pelaksana->id, [$pelaksana->id], $manager);
            $hasil[$k] = compact('c', 'manager', 'pic', 'pelaksana', 'client', 'divisi', 'jabatan', 'p');
        }

        return [$hasil['A'], $hasil['B']];
    }

    private function nama($m): string
    {
        return e($m->name);
    }

    public function test_filter_project_management_dan_task_hanya_dalam_cakupan(): void
    {
        [$a, $b] = $this->duaCompany();
        $m = $a['manager'];

        $html = $this->actingAs($m)->get('/projects')->assertOk()->getContent();
        foreach ([$a['c'], $a['client'], $a['pic']] as $x) {
            $this->assertStringContainsString($this->nama($x), $html);
        }
        foreach ([$b['c'], $b['client'], $b['pic']] as $x) {
            $this->assertStringNotContainsString($this->nama($x), $html);
        }

        $html = $this->actingAs($m)->get('/management/projects')->assertOk()->getContent();
        $this->assertStringContainsString($this->nama($a['c']), $html);
        $this->assertStringNotContainsString($this->nama($b['c']), $html);
        $this->assertStringNotContainsString($this->nama($b['client']), $html);

        $html = $this->actingAs($m)->get('/management/teams')->assertOk()->getContent();
        $this->assertStringContainsString($this->nama($a['divisi']), $html);
        $this->assertStringNotContainsString($this->nama($b['divisi']), $html);

        $html = $this->actingAs($m)->get('/tasks')->assertOk()->getContent();
        $this->assertStringContainsString($this->nama($a['pelaksana']), $html);
        $this->assertStringNotContainsString($this->nama($b['pelaksana']), $html);

        // Member hanya melihat pilihan dari project yang ia ikuti.
        $html = $this->actingAs($a['pelaksana'])->get('/projects')->assertOk()->getContent();
        $this->assertStringContainsString($this->nama($a['client']), $html);
        $this->assertStringNotContainsString($this->nama($b['client']), $html);
        $this->assertStringNotContainsString($this->nama($b['c']), $html);

        // Super Admin melihat semuanya.
        $html = $this->actingAs($this->pengguna('super_admin', $a['c']))->get('/projects')->assertOk()->getContent();
        $this->assertStringContainsString($this->nama($b['client']), $html);
        $this->assertStringContainsString($this->nama($b['pic']), $html);
    }

    public function test_data_master_manager_hanya_company_sendiri(): void
    {
        [$a, $b] = $this->duaCompany();
        $m = $a['manager'];
        $ambil = function (string $url, string $kolom) use ($m): array {
            $q = http_build_query(['draw' => 1, 'start' => 0, 'length' => 500,
                'columns' => [['data' => $kolom, 'name' => $kolom, 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);
            $r = $this->actingAs($m)->getJson($url.'?'.$q)->assertOk();
            $this->assertArrayNotHasKey('error', $r->json(), (string) $r->json('error'));

            return array_column($r->json('data'), $kolom);
        };

        $this->assertContains($a['c']->name, $ambil('/master/companies/data', 'name'));
        $this->assertNotContains($b['c']->name, $ambil('/master/companies/data', 'name'));
        $this->assertContains($a['client']->name, $ambil('/master/clients/data', 'name'));
        $this->assertNotContains($b['client']->name, $ambil('/master/clients/data', 'name'));
        $this->assertContains($a['divisi']->name, $ambil('/master/divisions/data', 'name'));
        $this->assertNotContains($b['divisi']->name, $ambil('/master/divisions/data', 'name'));
        $this->assertContains($a['jabatan']->name, $ambil('/master/positions/data', 'name'));
        $this->assertNotContains($b['jabatan']->name, $ambil('/master/positions/data', 'name'));
        $anggota = $ambil('/master/members/data', 'email');
        $this->assertContains($a['pelaksana']->email, $anggota);
        $this->assertNotContains($b['pelaksana']->email, $anggota);
    }

    public function test_ubah_master_di_luar_cakupan_ditolak_walau_punya_izin_kelola(): void
    {
        [$a, $b] = $this->duaCompany();
        $m = $a['manager'];
        // Super Admin bisa memberi role manager izin kelola; cakupan company tetap berlaku.
        $role = DB::table('roles')->where('slug', 'manager')->value('id');
        foreach (['companies.manage', 'divisions.manage', 'positions.manage', 'members.manage', 'clients.manage'] as $slug) {
            DB::table('role_permissions')->insertOrIgnore(['role_id' => $role, 'permission_id' => DB::table('permissions')->where('slug', $slug)->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        }
        $m->lupakanIzin();

        $this->actingAs($m)->get("/master/companies/{$a['c']->id}/edit")->assertOk();
        $this->actingAs($m)->get("/master/companies/{$b['c']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/divisions/{$a['divisi']->id}/edit")->assertOk();
        $this->actingAs($m)->get("/master/divisions/{$b['divisi']->id}/edit")->assertForbidden();
        $this->actingAs($m)->patch("/master/divisions/{$b['divisi']->id}/toggle")->assertForbidden();
        $this->actingAs($m)->get("/master/positions/{$b['jabatan']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/members/{$b['pelaksana']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/clients/{$b['client']->id}/edit")->assertForbidden();
        $this->actingAs($m)->get("/master/clients/{$a['client']->id}/edit")->assertOk();

        $form = $this->actingAs($m)->get('/master/divisions/create')->assertOk()->getContent();
        $this->assertStringContainsString($this->nama($a['c']), $form);
        $this->assertStringNotContainsString($this->nama($b['c']), $form);
        $this->assertStringNotContainsString($this->nama($b['pic']), $form);
        $this->actingAs($m)->post('/master/divisions', ['company_id' => $b['c']->id, 'name' => 'Divisi Selundupan', 'is_active' => 1])->assertSessionHasErrors('company_id');
        $this->assertFalse(Division::query()->where('name', 'Divisi Selundupan')->exists());

        $this->actingAs($m)->post('/master/members', ['name' => 'Calon Admin', 'email' => Str::random(8).'@uji.test', 'password' => 'sandi-uji-123456',
            'role' => 'super_admin', 'companies' => [$a['c']->id], 'is_active' => 1])->assertSessionHasErrors('role');
        $this->actingAs($m)->post('/master/members', ['name' => 'Anggota Luar', 'email' => Str::random(8).'@uji.test', 'password' => 'sandi-uji-123456',
            'role' => 'member', 'companies' => [$b['c']->id], 'is_active' => 1])->assertSessionHasErrors('companies.0');
    }
}
