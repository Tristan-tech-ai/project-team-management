<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\ProjectTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Technical Guide 5.1 and 6.3: Quick Add from the Tasks page, and a Task List with quick actions by status. */
class TaskQuickAddTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_quick_add_is_available_from_tasks_page(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a]);
        $cb = $this->company('Company Lain');
        $mb = $this->user('manager', $cb);
        $foreign = $this->project($mb, $mb, $cb);

        $this->actingAs($manager)->get('/tasks')->assertOk()->assertSee(route('tasks.create'), false);
        $choose = $this->actingAs($manager)->get('/tasks/create')->assertOk()->getContent();
        $this->assertStringContainsString(e($p->name), $choose);
        $this->assertStringNotContainsString(e($foreign->name), $choose);
        // U9: one screen, the project is chosen on the same form as the title and people (no "Lanjut" step).
        $this->assertStringNotContainsString('>Lanjut<', $choose);
        $this->assertStringContainsString('name="title"', $choose);
        $this->assertStringContainsString('data-members=', $choose);
        $this->assertStringContainsString($a->name, html_entity_decode($choose), 'members of the project are sent with the form');
        $form = $this->actingAs($manager)->get("/tasks/create?project={$p->id}")->assertOk()->getContent();
        $this->assertStringContainsString(route('projects.tasks.store', $p), $form);
        $this->assertStringContainsString(e($a->name), $form);

        $this->actingAs($manager)->post(route('projects.tasks.store', $p), ['title' => 'Banner Promo', 'pic_user_id' => $manager->id, 'assignees' => [$a->id]])->assertRedirect();
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Banner Promo')->firstOrFail();
        $this->assertSame('backlog', $t->status);
        $this->assertEqualsCanonicalizing([$manager->id, $a->id], $t->assignees()->pluck('users.id')->all());
        // A Member may add tasks only to projects they joined (matrix 4.2: own project tasks).
        $this->actingAs($a)->get('/tasks/create')->assertOk()->assertSee(e($p->name), false)->assertDontSee(e($foreign->name), false);
    }

    public function test_task_list_offers_quick_actions_by_status(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($t, $a->id, [$a->id], $manager);
        $q = http_build_query(['draw' => 1, 'start' => 0, 'length' => 50, 'columns' => [['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true']], 'order' => [['column' => 0, 'dir' => 'asc']]]);

        $rows = collect($this->actingAs($a)->getJson(route('tasks.mine.data').'?'.$q)->assertOk()->json('data'))->firstWhere('title', 'Desain');
        $this->assertSame(['Mulai', 'Buka'], array_column($rows['actions'], 'label'));
        $this->assertSame(['status' => 'in_progress'], $rows['actions'][0]['fields']);
        $this->actingAs($a)->patch($rows['actions'][0]['url'], $rows['actions'][0]['fields'])->assertRedirect();
        $this->assertSame('in_progress', $t->refresh()->status);

        $rows = collect($this->actingAs($a)->getJson(route('tasks.mine.data').'?'.$q)->json('data'))->firstWhere('title', 'Desain');
        $this->assertSame(['Kirim hasil', 'Buka'], array_column($rows['actions'], 'label'));

        // A task they do not hold: only Open (no action the server would refuse).
        $other = collect($this->actingAs($a)->getJson('/projects/'.$p->id.'/tasks/data?'.$q)->json('data'))->firstWhere('title', 'Analisis');
        $this->assertSame(['Buka'], array_column($other['actions'], 'label'));
    }
}
