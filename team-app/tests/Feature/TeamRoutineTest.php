<?php

namespace Tests\Feature;

use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Services\ProjectTemplateService;
use App\Services\ProjectTeamService;
use App\Support\Dates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Case study 19 Nov: a member ticks today's routine ("Admin sosmed, every day") straight from the Team App home. */
class TeamRoutineTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_todays_routine_is_on_the_home_and_ticks_there(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-10 09:00', Dates::zone()));
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $template = ProjectTemplate::create(['name' => 'Rutin Uji '.uniqid(), 'project_type' => 'recurring', 'is_active' => 1]);
        $template->tasks()->create(['title' => 'Admin sosmed', 'task_type' => 'general', 'sort_order' => 0])
            ->routine()->create(['repeat_rule' => 'daily', 'start_time' => '10:00:00', 'end_time' => '15:00:00']);
        $project = app(ProjectTemplateService::class)->createProject(['company_id' => $c->id, 'name' => 'Sosmed Uji', 'project_type' => 'recurring',
            'pic_user_id' => $manager->id, 'project_template_id' => $template->id, 'members' => [$member->id]], $manager);
        $routine = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($routine, $member->id, [$member->id], $manager);

        $home = $this->actingAs($member)->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('Rutin hari ini', $home);
        $this->assertStringContainsString('10.00-15.00 · Sosmed Uji', $home);
        $today = $routine->subtasks()->where('title', 'Kamis, 10 Des 10.00-15.00')->firstOrFail();
        $this->assertStringContainsString('data-check-subtask="'.route('subtasks.toggle', $today).'"', $home);

        $this->actingAs($member)->patchJson(route('subtasks.toggle', $today), ['done' => true])->assertOk()->assertJson(['done' => true]);
        $this->assertSame('in_progress', $routine->refresh()->status);
        $this->assertStringNotContainsString('Rutin hari ini', $this->actingAs($member)->get(route('home'))->getContent(), 'done for today');
    }
}
