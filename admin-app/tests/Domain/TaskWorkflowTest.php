<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Services\ProjectTeamService;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** LV-03: status, multi-assignee, checklist, review, revisi beralasan. Dijalankan identik di admin-app dan team-app. */
class TaskWorkflowTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    private function siapkan(): array
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $a = $this->pengguna('member', $c);
        $b = $this->pengguna('member', $c);
        $luar = $this->pengguna('member', $c);
        $p = $this->project($manager, $manager, $c, [$a, $b, $luar], [], 'one_time', $this->template());
        $task = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->aturAssignee($task, $a->id, [$a->id, $b->id], $manager);

        return [$manager, $a, $b, $luar, $task->refresh(), $p];
    }

    private function wf(): TaskWorkflowService
    {
        return app(TaskWorkflowService::class);
    }

    public function test_task_dua_pelaksana_dan_pic_termasuk_assignee(): void
    {
        [$manager, $a, $b, , $task] = $this->siapkan();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $task->assignees()->pluck('users.id')->all());
        $this->assertSame($a->id, $task->pic_user_id);
        $this->assertTrue($a->can('moveStatus', $task));
        $this->assertTrue($b->can('moveStatus', $task));
    }

    public function test_pelaksana_harus_anggota_project(): void
    {
        [$manager, $a, , , $task] = $this->siapkan();
        $asing = $this->pengguna('member', $this->company('Lain'));
        $this->expectException(ValidationException::class);
        app(ProjectTeamService::class)->aturAssignee($task, $a->id, [$asing->id], $manager);
    }

    public function test_member_bukan_pelaksana_tidak_bisa_memindah_status(): void
    {
        [, , , $luar, $task] = $this->siapkan();
        $this->expectException(ValidationException::class);
        $this->wf()->pindah($task, 'in_progress', $luar);
    }

    public function test_alur_mulai_kirim_revisi_lalu_terima(): void
    {
        [$manager, $a, $b, , $task] = $this->siapkan();
        $t = $this->wf()->pindah($task, 'in_progress', $b);
        $this->assertSame('in_progress', $t->status);
        $this->assertNotNull($t->start_at);

        // Anggota tidak bisa melewati Review: pindah langsung ke review atau done ditolak.
        foreach (['review', 'done'] as $ke) {
            try {
                $this->wf()->pindah($t, $ke, $a);
                $this->fail("pindah ke {$ke} seharusnya ditolak");
            } catch (ValidationException) {
            }
        }

        $sub = $this->wf()->kirimHasil($t, $a, 'https://contoh.test/hasil-desain', null, 'Versi pertama');
        $this->assertSame('review', $t->refresh()->status);
        $this->assertSame(1, $sub->attachments()->count());

        // Pelaksana Member tidak bisa menerima hasilnya sendiri.
        try {
            $this->wf()->putuskan($sub, 'approved', $a, null);
            $this->fail('Member seharusnya tidak bisa menerima hasil');
        } catch (ValidationException) {
        }
        // Revisi wajib beralasan.
        try {
            $this->wf()->putuskan($sub, 'revision', $manager, '');
            $this->fail('Revisi tanpa alasan seharusnya ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('review_note', $e->errors());
        }
        $t = $this->wf()->putuskan($sub, 'revision', $manager, 'Warna tombol belum sesuai brand');
        $this->assertSame('in_progress', $t->status);
        $this->assertSame('revision', $sub->refresh()->status);

        $sub2 = $this->wf()->kirimHasil($t, $b, 'https://contoh.test/hasil-desain-2', null, 'Sudah diperbaiki');
        $t = $this->wf()->putuskan($sub2, 'approved', $manager, null);
        $this->assertSame('done', $t->status);
        $this->assertNotNull($t->completed_at);
        $this->assertSame($manager->id, $t->completed_by);
        // Riwayat submission tidak dihapus.
        $this->assertSame(2, $t->submissions()->count());
        $this->assertGreaterThanOrEqual(4, TaskActivity::query()->where('task_id', $t->id)->count());
    }

    public function test_buka_kembali_mengosongkan_completed(): void
    {
        [$manager, $a, , , $task] = $this->siapkan();
        $t = $this->wf()->pindah($task, 'in_progress', $a);
        $sub = $this->wf()->kirimHasil($t, $a, 'https://contoh.test/x', null, null);
        $t = $this->wf()->putuskan($sub, 'approved', $manager, null);
        $t = $this->wf()->pindah($t, 'in_progress', $manager);
        $this->assertNull($t->completed_at);
        $this->assertNull($t->completed_by);
    }

    public function test_kirim_hasil_wajib_tautan_atau_berkas(): void
    {
        [, $a, , , $task] = $this->siapkan();
        $this->expectException(ValidationException::class);
        $this->wf()->kirimHasil($task, $a, null, null, 'tanpa bukti');
    }

    public function test_checklist_satu_tingkat_dan_progress(): void
    {
        [, $a, , $luar, $task] = $this->siapkan();
        $subs = $task->subtasks()->orderBy('sort_order')->get();
        $s = $this->wf()->centang($subs[0], $a, true);
        $this->assertSame('done', $s->status);
        $this->assertNotNull($s->completed_at);
        $this->assertSame(50, TaskWorkflowService::progresTask($task->subtasks()->where('status', 'done')->count(), $task->subtasks()->count()));
        $s = $this->wf()->centang($subs[0], $a, false);
        $this->assertNull($s->completed_at);
        // Bukan pelaksana tidak bisa mencentang; subtask tidak bisa dipindah kolom.
        try {
            $this->wf()->centang($subs[1], $luar, true);
            $this->fail('non-pelaksana seharusnya ditolak');
        } catch (ValidationException) {
        }
        $this->expectException(ValidationException::class);
        $this->wf()->pindah($subs[1], 'in_progress', $a);
    }
}
