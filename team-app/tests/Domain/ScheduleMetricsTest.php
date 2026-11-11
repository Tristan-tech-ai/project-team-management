<?php

namespace Tests\Domain;

use App\Models\ProjectActivity;
use App\Models\Task;
use App\Services\ManagementMetricsService;
use App\Services\ScheduleChangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** LV-04 dan LV-05: perubahan deadline dengan baseline tetap, aktivitas tidak menggeser deadline, overdue dinamis, KPI. */
class ScheduleMetricsTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_hanya_pengajuan_disetujui_mengubah_deadline_dan_baseline_tetap(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $member = $this->pengguna('member', $c);
        $p = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $task = Task::query()->where('project_id', $p->id)->where('title', 'Rilis')->firstOrFail();
        $baseline = $task->baseline_due_at->copy();

        $rapat = ProjectActivity::create(['project_id' => $p->id, 'task_id' => $task->id, 'created_by' => $member->id, 'activity_type' => 'meeting',
            'title' => 'Rapat klien tambahan', 'status' => 'done', 'occurred_at' => now(), 'duration_minutes' => 120, 'impact_type' => 'delay']);
        $this->assertTrue($task->refresh()->due_at->equalTo($baseline), 'aktivitas tidak boleh menggeser deadline otomatis');

        $svc = app(ScheduleChangeService::class);
        $r = $svc->ajukan($p, $task, $rapat, now()->addDays(20), 'Klien minta revisi alur', $member);
        $this->assertSame('pending', $r->status);
        $this->assertTrue($task->refresh()->due_at->equalTo($baseline));
        // Pengajuan ganda untuk task yang sama ditolak sampai diputuskan.
        try {
            $svc->ajukan($p, $task, null, now()->addDays(25), 'Lagi', $member);
            $this->fail('pengajuan ganda seharusnya ditolak');
        } catch (ValidationException) {
        }
        // Member tidak bisa memutuskan.
        try {
            $svc->putuskan($r, true, null, $member);
            $this->fail('member seharusnya tidak bisa memutuskan');
        } catch (ValidationException) {
        }
        $r = $svc->putuskan($r, true, null, $manager);
        $task->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertNotNull($r->applied_at);
        $this->assertTrue($task->due_at->equalTo($r->proposed_due_at));
        $this->assertTrue($task->baseline_due_at->equalTo($baseline), 'baseline_due_at tidak boleh berubah');
        $this->assertTrue($r->previous_due_at->equalTo($baseline));
    }

    public function test_penolakan_wajib_alasan_dan_tidak_mengubah_deadline(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c);
        $svc = app(ScheduleChangeService::class);
        $r = $svc->ajukan($p, null, null, now()->addDays(30), 'Tambah ruang lingkup', $manager);
        try {
            $svc->putuskan($r, false, '', $manager);
            $this->fail('penolakan tanpa alasan seharusnya ditolak');
        } catch (ValidationException) {
        }
        $due = $p->refresh()->due_at->copy();
        $r = $svc->putuskan($r, false, 'Ruang lingkup tetap', $manager);
        $this->assertSame('rejected', $r->status);
        $this->assertNull($r->applied_at);
        $this->assertTrue($p->refresh()->due_at->equalTo($due));
    }

    public function test_overdue_dinamis_dan_kpi_membedakan_anggota_unik_dari_penugasan(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $a = $this->pengguna('member', $c);
        $p = $this->project($manager, $manager, $c, [$a], [], 'one_time', $this->template());
        $utama = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->get();
        foreach ($utama as $t) {
            DB::table('task_assignees')->insertOrIgnore(['task_id' => $t->id, 'user_id' => $a->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        Task::query()->whereKey($utama[0]->id)->update(['due_at' => now()->subDay()]);
        Task::query()->whereKey($utama[1]->id)->update(['due_at' => now()->subDay(), 'status' => 'done', 'completed_at' => now()]);

        $k = app(ManagementMetricsService::class)->kpi($manager);
        $this->assertSame(1, Task::query()->where('project_id', $p->id)->whereNull('parent_id')->where('status', '!=', 'done')->where('due_at', '<', now())->count());
        $this->assertGreaterThanOrEqual(1, $k['terlambat']);
        $baris = app(ManagementMetricsService::class)->monitoring($manager)->firstWhere('id', $p->id);
        $this->assertSame(2, (int) $baris->anggota);
        $this->assertSame(1, (int) $baris->task_terlambat);
        $this->assertSame('delayed', $baris->kondisi);
        $this->assertSame(33, $baris->progres);
        $hitung = \App\Services\ProjectTeamService::hitung($p);
        $this->assertSame(2, $hitung['orang']);
        $this->assertSame(6, $hitung['penugasan']);
    }
}
