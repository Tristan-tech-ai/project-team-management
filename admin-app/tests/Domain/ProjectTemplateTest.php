<?php

namespace Tests\Domain;

use App\Models\Project;
use App\Models\Task;
use App\Services\ManagementMetricsService;
use App\Services\ProjectTeamService;
use App\Services\ProjectTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** LV-02: project dari template, satu transaksi, tipe, periode, anggota unik, progress. */
class ProjectTemplateTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_template_menghasilkan_semua_task_dan_subtask_dan_pic_menjadi_anggota_serta_assignee(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $this->assertSame(3, Task::query()->where('project_id', $p->id)->whereNull('parent_id')->count());
        $this->assertSame(2, Task::query()->where('project_id', $p->id)->whereNotNull('parent_id')->count());
        $this->assertSame(['Wireframe', 'Mockup'], Task::query()->where('project_id', $p->id)->whereNotNull('parent_id')->orderBy('sort_order')->pluck('title')->all());
        $this->assertTrue(DB::table('project_members')->where('project_id', $p->id)->where('user_id', $manager->id)->exists());
        $utama = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->pluck('id');
        $this->assertSame($utama->count(), DB::table('task_assignees')->whereIn('task_id', $utama)->where('user_id', $manager->id)->count());
        $this->assertNotNull(Task::query()->where('project_id', $p->id)->where('title', 'Desain')->value('baseline_due_at'));
    }

    public function test_pratinjau_tidak_menulis_apa_pun(): void
    {
        $t = $this->template();
        $sebelum = Task::query()->count();
        $pr = app(ProjectTemplateService::class)->pratinjau($t);
        $this->assertCount(3, $pr);
        $this->assertSame(['Wireframe', 'Mockup'], $pr[1]['subtasks']);
        $this->assertSame($sebelum, Task::query()->count());
    }

    public function test_gagal_di_tengah_membatalkan_semuanya(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $bukanAnggotaCompany = $this->pengguna('member');
        $sebelum = [Project::withTrashed()->count(), Task::query()->count()];
        try {
            $this->project($manager, $bukanAnggotaCompany, $c, [], [], 'one_time', $this->template());
            $this->fail('PIC di luar company seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('members', $e->errors());
        }
        $this->assertSame($sebelum, [Project::withTrashed()->count(), Task::query()->count()]);
    }

    public function test_divisi_harus_dari_company_project(): void
    {
        $c = $this->company();
        $lain = $this->company('Lain');
        $manager = $this->pengguna('manager', $c);
        $this->expectException(ValidationException::class);
        $this->project($manager, $manager, $c, [], [$this->divisi($lain, 'Asing')]);
    }

    public function test_anggota_unik_tidak_ganda_walau_di_beberapa_divisi(): void
    {
        $c = $this->company();
        $dev = $this->divisi($c, 'Development');
        $kreatif = $this->divisi($c, 'Creative');
        $manager = $this->pengguna('manager', $c, [$dev]);
        $ganda = $this->pengguna('member', $c, [$dev, $kreatif]);
        $p = $this->project($manager, $manager, $c, [$ganda, $ganda], [$dev, $kreatif]);
        $h = ProjectTeamService::hitung($p);
        $this->assertSame(2, $h['orang']);
        $this->assertSame(2, $h['divisi']);
        $pm = DB::table('project_members')->where('project_id', $p->id)->where('user_id', $ganda->id)->value('id');
        $this->assertSame(2, DB::table('project_member_divisions')->where('project_member_id', $pm)->count());
    }

    public function test_recurring_membuka_periode_baru_tanpa_menutup_induk_dan_task_lama_tetap_di_periode_lama(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'recurring', $this->template('recurring'));
        $periode1 = $p->periods()->first();
        $this->assertNotNull($periode1);
        $this->assertSame(5, Task::query()->where('project_period_id', $periode1->id)->count());
        $periode2 = app(ProjectTemplateService::class)->bukaPeriode($p, $manager);
        $this->assertNotSame($periode1->period_code, $periode2->period_code);
        $this->assertSame('closed', $periode1->refresh()->status);
        $this->assertSame('active', $p->refresh()->status);
        $this->assertSame(5, Task::query()->where('project_period_id', $periode1->id)->count());
        $this->assertSame(5, Task::query()->where('project_period_id', $periode2->id)->count());
    }

    public function test_one_time_tidak_punya_periode(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c);
        $this->assertSame(0, $p->periods()->count());
        $this->expectException(ValidationException::class);
        app(ProjectTemplateService::class)->bukaPeriode($p, $manager);
    }

    public function test_progress_project_tanpa_subtask_di_penyebut(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        Task::query()->where('project_id', $p->id)->whereNull('parent_id')->where('title', 'Analisis')->update(['status' => 'done', 'completed_at' => now()]);
        Task::query()->where('project_id', $p->id)->whereNotNull('parent_id')->update(['status' => 'done']);
        $pr = ManagementMetricsService::progresProject($p->id);
        $this->assertSame(['total' => 3, 'selesai' => 1, 'persen' => 33], $pr);
    }
}
