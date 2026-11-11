<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\Pembantu;
use Tests\TestCase;

/** Aplikasi Tim: login, navigasi mobile/desktop, task kolaboratif terlihat semua pelaksana, endpoint status sama dengan Admin. */
class TimAksesTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_tamu_diarahkan_dan_login_member_berhasil(): void
    {
        $this->get('/tasks')->assertRedirect('/masuk');
        $u = $this->pengguna('member', $this->company());
        $this->post('/masuk', ['email' => $u->email, 'password' => 'sandi-uji-123456'])->assertRedirect(route('tasks.index')); // kembali ke halaman yang semula dituju (redirect intended)
        $this->assertAuthenticatedAs($u);
    }

    public function test_bottom_nav_hanya_hp_dan_sidebar_hanya_desktop(): void
    {
        $u = $this->pengguna('member', $this->company());
        $html = $this->actingAs($u)->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<nav class="mobile-bottom-nav d-lg-none"/', $html);
        $this->assertMatchesRegularExpression('/<nav class="desktop-sidebar d-none d-lg-flex"/', $html);
        foreach (['Beranda', 'Tasks', 'Kalender', 'Profil'] as $label) {
            $this->assertStringContainsString($label.'</a>', $html);
        }
    }

    public function test_task_kolaboratif_terlihat_di_task_saya_kedua_pelaksana(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $a = $this->pengguna('member', $c);
        $b = $this->pengguna('member', $c);
        $p = $this->project($manager, $manager, $c, [$a, $b]);
        $this->actingAs($manager)->post("/projects/{$p->id}/tasks", ['title' => 'Landing Page Kampanye', 'pic_user_id' => $a->id, 'assignees' => [$b->id]])->assertRedirect();
        $t = Task::query()->where('title', 'Landing Page Kampanye')->firstOrFail();
        $this->assertSame('backlog', $t->status);
        foreach ([$a, $b] as $u) {
            $this->actingAs($u)->get('/tasks')->assertOk()->assertSee('Landing Page Kampanye');
        }
        $luar = $this->pengguna('member', $c);
        $this->actingAs($luar)->get("/tasks/{$t->id}")->assertForbidden();
    }

    public function test_quick_add_menolak_pelaksana_di_luar_project(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $luar = $this->pengguna('member', $c);
        $p = $this->project($manager, $manager, $c);
        $this->actingAs($manager)->post("/projects/{$p->id}/tasks", ['title' => 'Coba', 'pic_user_id' => $manager->id, 'assignees' => [$luar->id]])->assertSessionHasErrors('assignees');
        $this->assertFalse(Task::query()->where('title', 'Coba')->where('project_id', $p->id)->exists());
    }

    public function test_endpoint_status_dan_checklist_sama_dengan_admin(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $a = $this->pengguna('member', $c);
        $p = $this->project($manager, $a, $c, [$manager], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        $this->actingAs($a)->patchJson("/tasks/{$t->id}/status", ['status' => 'review'])->assertStatus(422);
        $this->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk();
        $s = $t->subtasks()->first();
        $this->patchJson("/subtasks/{$s->id}/toggle", ['selesai' => true])->assertOk()->assertJson(['selesai' => true, 'progres' => 50, 'jumlah' => '1 / 2']);
    }

    public function test_member_tidak_bisa_membuka_review_tanpa_izin(): void
    {
        $u = $this->pengguna('member', $this->company());
        $this->actingAs($u)->get('/review')->assertForbidden();
    }
}
