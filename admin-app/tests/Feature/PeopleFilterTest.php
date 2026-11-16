<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Services\ProjectTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** I5: people lists carry a name search hook and, on the task, the open task count beside each name. */
class PeopleFilterTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_people_lists_offer_search_and_workload(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $ayu = $this->user('member', $c);
        $project = $this->project($manager, $manager, $c, [$ayu], [], 'one_time', $this->template());
        $tasks = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('sort_order')->get();
        foreach ($tasks->take(2) as $t) {
            app(ProjectTeamService::class)->setAssignees($t, $ayu->id, [$ayu->id], $manager);
        }

        $detail = $this->actingAs($manager)->get(route('tasks.show', $tasks[0]))->assertOk()->getContent();
        $this->assertStringContainsString('data-people-filter=".form-check"', $detail);
        $this->assertMatchesRegularExpression('/for="as-'.$ayu->id.'">'.preg_quote(e($ayu->name), '/').' <span class="load-count"[^>]*>2<\/span>/', $detail);

        $this->assertStringContainsString('data-people-filter="label"', $this->actingAs($manager)->get(route('tasks.create'))->assertOk()->getContent());
        $admin = $this->user('super_admin');
        $this->assertStringContainsString('data-people-filter="span[data-company]"', $this->actingAs($admin)->get(route('projects.create'))->assertOk()->getContent());
        $this->assertStringContainsString('data-people-filter="label"', $this->actingAs($admin)->get(route('projects.team', $project))->assertOk()->getContent());
    }
}
