<?php

namespace Tests\Domain;

use App\Models\ProjectActivity;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** LV-04: kalender memisahkan deadline, kegiatan, publikasi, dan selesai aktual; tidak memakai updated_at. Endpoint sama di kedua app. */
class KalenderTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_mode_kalender_terpisah(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c);
        $mulai = now()->startOfMonth();
        $deadline = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Deadline saja',
            'status' => 'in_progress', 'due_at' => $mulai->copy()->addDays(10)]);
        $konten = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Feed promo',
            'status' => 'backlog', 'task_type' => 'content', 'platform' => 'Instagram', 'scheduled_publish_at' => $mulai->copy()->addDays(12), 'publication_status' => 'scheduled']);
        $selesai = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Sudah beres',
            'status' => 'done', 'completed_at' => $mulai->copy()->addDays(3), 'completed_by' => $manager->id]);
        // updated_at sengaja di dalam rentang, completed_at tidak: tidak boleh muncul di mode Selesai pada rentang updated_at saja.
        $basi = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Diubah saja', 'status' => 'in_progress']);
        ProjectActivity::create(['project_id' => $p->id, 'created_by' => $manager->id, 'activity_type' => 'visit', 'title' => 'Kunjungan klien',
            'status' => 'done', 'occurred_at' => $mulai->copy()->addDays(5), 'duration_minutes' => 90, 'impact_type' => 'none']);
        $rentang = ['start' => $mulai->copy()->subDay()->toIso8601String(), 'end' => $mulai->copy()->addMonth()->toIso8601String(), 'project_id' => $p->id];
        $judul = fn ($mode) => collect($this->actingAs($manager)->getJson('/calendar/events?'.http_build_query($rentang + ['mode' => $mode]))->assertOk()->json())->pluck('title')->all();

        $this->assertSame(['Deadline: Deadline saja'], $judul('jadwal'));
        $this->assertSame(['Jadwal terbit: Feed promo (Instagram)'], $judul('publikasi'));
        $this->assertSame(['Selesai: Sudah beres'], $judul('selesai'));
        $this->assertSame(['Kunjungan: Kunjungan klien'], $judul('aktivitas'));
        $this->assertNotContains('Selesai: Diubah saja', $judul('selesai'));
    }

    public function test_kalender_hanya_project_dalam_scope(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $member = $this->pengguna('member', $c);
        $p = $this->project($manager, $manager, $c);
        Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Rahasia', 'status' => 'backlog', 'due_at' => now()->addDay()]);
        $ev = $this->actingAs($member)->getJson('/calendar/events?'.http_build_query(['mode' => 'jadwal', 'start' => now()->subWeek()->toIso8601String(), 'end' => now()->addWeek()->toIso8601String()]))->assertOk()->json();
        $this->assertSame([], $ev);
    }
}
