<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\ProjectTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** I3 on the Team App: My Tasks cards open the side panel (full screen on phones); the panel keeps the Team App access rule. */
class TeamTaskPanelTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_my_tasks_cards_open_the_side_panel(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $project = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $task = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('sort_order')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($task, $member->id, [$member->id], $manager);

        $list = $this->actingAs($member)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*\/tasks\/'.$task->id.'" class="task-tile-title stretched-link" data-task-panel/', $list);
        $this->assertStringContainsString('id="task-panel"', $list);
        $this->assertStringContainsString('data-refresh-region="task-cards"', $list);

        $panel = $this->actingAs($member)->get(route('tasks.show', $task).'?panel=1')->assertOk()->getContent();
        $this->assertStringContainsString('data-task-panel-content', $panel);
        $this->assertStringNotContainsString('mobile-bottom-nav', $panel, 'no layout inside the panel');
        $this->assertStringContainsString('Kirim hasil untuk review', $panel);

        $outsider = $this->user('member', $c);
        $this->actingAs($outsider)->get(route('tasks.show', $task).'?panel=1')->assertForbidden();
    }
}
