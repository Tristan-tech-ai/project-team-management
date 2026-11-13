<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Team App: login, mobile/desktop navigation, collaborative tasks visible to every assignee, status endpoint same as the Admin App. */
class TeamAccessTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_guest_is_redirected_and_member_login_succeeds(): void
    {
        $this->get('/tasks')->assertRedirect('/login');
        $u = $this->user('member', $this->company());
        $this->post('/login', ['email' => $u->email, 'password' => 'test-password-123456'])->assertRedirect(route('tasks.index')); // back to the originally requested page (redirect intended)
        $this->assertAuthenticatedAs($u);
    }

    public function test_bottom_nav_only_on_mobile_and_sidebar_only_on_desktop(): void
    {
        $u = $this->user('member', $this->company());
        $html = $this->actingAs($u)->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<nav class="mobile-bottom-nav d-lg-none"/', $html);
        $this->assertMatchesRegularExpression('/<nav class="desktop-sidebar d-none d-lg-flex"/', $html);
        foreach (['Beranda', 'Tasks', 'Kalender', 'Profil'] as $label) {
            $this->assertStringContainsString($label.'</a>', $html);
        }
    }

    public function test_collaborative_task_shows_in_my_tasks_for_both_assignees(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $b = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a, $b]);
        $this->actingAs($manager)->post("/projects/{$p->id}/tasks", ['title' => 'Landing Page Kampanye', 'pic_user_id' => $a->id, 'assignees' => [$b->id]])->assertRedirect();
        $t = Task::query()->where('title', 'Landing Page Kampanye')->firstOrFail();
        $this->assertSame('backlog', $t->status);
        foreach ([$a, $b] as $u) {
            $this->actingAs($u)->get('/tasks')->assertOk()->assertSee('Landing Page Kampanye');
        }
        $outsider = $this->user('member', $c);
        $this->actingAs($outsider)->get("/tasks/{$t->id}")->assertForbidden();
    }

    public function test_quick_add_rejects_assignees_outside_project(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $outsider = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c);
        $this->actingAs($manager)->post("/projects/{$p->id}/tasks", ['title' => 'Coba', 'pic_user_id' => $manager->id, 'assignees' => [$outsider->id]])->assertSessionHasErrors('assignees');
        $this->assertFalse(Task::query()->where('title', 'Coba')->where('project_id', $p->id)->exists());
    }

    public function test_status_and_checklist_endpoints_match_admin(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $p = $this->project($manager, $a, $c, [$manager], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        $this->actingAs($a)->patchJson("/tasks/{$t->id}/status", ['status' => 'review'])->assertStatus(422);
        $this->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk();
        $s = $t->subtasks()->first();
        $this->patchJson("/subtasks/{$s->id}/toggle", ['done' => true])->assertOk()->assertJson(['done' => true, 'progress' => 50, 'total' => '1 / 2']);
    }

    public function test_member_cannot_open_review_without_permission(): void
    {
        $u = $this->user('member', $this->company());
        $this->actingAs($u)->get('/review')->assertForbidden();
    }
}
