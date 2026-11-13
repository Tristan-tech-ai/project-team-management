<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/**
 * Supervisor revision + Technical Guide 5.2 ("My Projects: member projects only"): the project list, summary, and Kanban
 * in the Team App use the same rule, including when opened directly by URL. A project that is not in the list
 * is always refused with 403. Managers see every project of their company in the Admin App, not in the Team App.
 */
class TeamProjectScopeTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_list_detail_and_kanban_only_show_member_projects(): void
    {
        $ca = $this->company('Company Tim A');
        $cb = $this->company('Company Tim B');
        $managerA = $this->user('manager', $ca);
        $managerB = $this->user('manager', $cb);
        $member = $this->user('member', $ca);
        $teammate = $this->user('member', $ca);
        $joined = $this->project($managerA, $teammate, $ca, [$member, $teammate, $managerA]);
        $sameCompany = $this->project($managerA, $teammate, $ca, [$teammate], [], 'one_time', $this->template());
        $foreign = $this->project($managerB, $managerB, $cb);

        $check = function ($user, array $can, array $reject): void {
            $list = $this->actingAs($user)->get('/projects')->assertOk()->getContent();
            $home = $this->actingAs($user)->get('/')->assertOk()->getContent();
            foreach ($can as $p) {
                $this->assertStringContainsString(e($p->name), $list, "{$p->name} must be in the list");
                $this->actingAs($user)->get("/projects/{$p->id}")->assertOk();
                $this->actingAs($user)->get("/projects/{$p->id}/board")->assertOk();
            }
            foreach ($reject as $p) {
                $this->assertStringNotContainsString(e($p->name), $list, "{$p->name} must not be in the list");
                $this->assertStringNotContainsString(e($p->name), $home, "{$p->name} must not be on the home page");
                $this->actingAs($user)->get("/projects/{$p->id}")->assertForbidden();
                $this->actingAs($user)->get("/projects/{$p->id}/board")->assertForbidden();
                $this->actingAs($user)->get("/projects/{$p->id}/board?period=1")->assertForbidden();
                $this->actingAs($user)->get("/projects/{$p->id}/activities/create")->assertForbidden();
                $this->assertStringNotContainsString(e($p->name), $this->actingAs($user)->get("/tasks/create?project={$p->id}")->getContent());
            }
        };

        $check($member, [$joined], [$sameCompany, $foreign]);
        // Manager: in the Team App only projects they joined or hold as PIC, even within the same company.
        $check($managerA, [$joined], [$sameCompany, $foreign]);
        $check($managerB, [$foreign], [$joined, $sameCompany]);

        // Task detail and Kanban actions by URL are also refused for tasks in projects the user has not joined.
        $t = Task::query()->where('project_id', $sameCompany->id)->whereNull('parent_id')->firstOrFail();
        $this->actingAs($member)->get("/tasks/{$t->id}")->assertForbidden();
        $this->actingAs($member)->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertForbidden();
        $this->actingAs($managerA)->get("/tasks/{$t->id}")->assertForbidden();
    }
}
