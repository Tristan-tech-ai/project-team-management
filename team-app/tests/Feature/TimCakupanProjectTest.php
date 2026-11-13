<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\Pembantu;
use Tests\TestCase;

/**
 * Revisi pembina: daftar, detail, dan Kanban project di Team App memakai pembatasan akses yang sama,
 * termasuk ketika halaman dibuka langsung lewat URL. Project yang tidak ada di daftar selalu ditolak 403.
 */
class TimCakupanProjectTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_daftar_detail_dan_kanban_konsisten_untuk_member_dan_manager(): void
    {
        $ca = $this->company('Company Tim A');
        $cb = $this->company('Company Tim B');
        $managerA = $this->pengguna('manager', $ca);
        $managerB = $this->pengguna('manager', $cb);
        $member = $this->pengguna('member', $ca);
        $rekan = $this->pengguna('member', $ca);
        $diikuti = $this->project($managerA, $rekan, $ca, [$member, $rekan]);
        $sekompany = $this->project($managerA, $rekan, $ca, [$rekan], [], 'one_time', $this->template());
        $asing = $this->project($managerB, $managerB, $cb);

        $cek = function ($user, array $boleh, array $tolak): void {
            $daftar = $this->actingAs($user)->get('/projects')->assertOk()->getContent();
            foreach ($boleh as $p) {
                $this->assertStringContainsString(e($p->name), $daftar, "{$p->name} harus ada di daftar");
                $this->actingAs($user)->get("/projects/{$p->id}")->assertOk();
                $this->actingAs($user)->get("/projects/{$p->id}/board")->assertOk();
            }
            foreach ($tolak as $p) {
                $this->assertStringNotContainsString(e($p->name), $daftar, "{$p->name} tidak boleh ada di daftar");
                $this->actingAs($user)->get("/projects/{$p->id}")->assertForbidden();
                $this->actingAs($user)->get("/projects/{$p->id}/board")->assertForbidden();
                $this->actingAs($user)->get("/projects/{$p->id}/board?periode=1")->assertForbidden();
            }
        };

        // Member: hanya project yang ia ikuti.
        $cek($member, [$diikuti], [$sekompany, $asing]);
        // Manager: project di company tempat ia anggota aktif (aturan yang sama dengan Admin App), bukan company lain.
        $cek($managerA, [$diikuti, $sekompany], [$asing]);
        $cek($managerB, [$asing], [$diikuti, $sekompany]);

        // Aksi Kanban lewat URL juga ditolak untuk task di project asing.
        $t = Task::query()->where('project_id', $sekompany->id)->whereNull('parent_id')->firstOrFail();
        $this->actingAs($member)->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertForbidden();
        $this->actingAs($member)->get("/tasks/{$t->id}")->assertForbidden();
    }
}
