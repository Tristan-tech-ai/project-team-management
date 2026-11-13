<?php

namespace Tests\Domain;

use App\Models\Project;
use App\Models\Task;
use App\Services\ManagementMetricsService;
use App\Services\ProjectTeamService;
use App\Services\ProjectTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** LV-02: project from a template, one transaction, type, periods, unique members, progress. */
class ProjectTemplateTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_template_generates_all_tasks_and_subtasks_and_pic_becomes_member_and_assignee(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $this->assertSame(3, Task::query()->where('project_id', $p->id)->whereNull('parent_id')->count());
        $this->assertSame(2, Task::query()->where('project_id', $p->id)->whereNotNull('parent_id')->count());
        $this->assertSame(['Wireframe', 'Mockup'], Task::query()->where('project_id', $p->id)->whereNotNull('parent_id')->orderBy('sort_order')->pluck('title')->all());
        $this->assertTrue(DB::table('project_members')->where('project_id', $p->id)->where('user_id', $manager->id)->exists());
        $main = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->pluck('id');
        $this->assertSame($main->count(), DB::table('task_assignees')->whereIn('task_id', $main)->where('user_id', $manager->id)->count());
        $this->assertNotNull(Task::query()->where('project_id', $p->id)->where('title', 'Desain')->value('baseline_due_at'));
    }

    public function test_preview_writes_nothing(): void
    {
        $t = $this->template();
        $before = Task::query()->count();
        $pr = app(ProjectTemplateService::class)->preview($t);
        $this->assertCount(3, $pr);
        $this->assertSame(['Wireframe', 'Mockup'], $pr[1]['subtasks']);
        $this->assertSame($before, Task::query()->count());
    }

    public function test_failure_midway_rolls_back_everything(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $nonCompanyMember = $this->user('member');
        $before = [Project::withTrashed()->count(), Task::query()->count()];
        try {
            $this->project($manager, $nonCompanyMember, $c, [], [], 'one_time', $this->template());
            $this->fail('a PIC outside the company must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('members', $e->errors());
        }
        $this->assertSame($before, [Project::withTrashed()->count(), Task::query()->count()]);
    }

    public function test_project_divisions_must_belong_to_the_project_company(): void
    {
        $c = $this->company();
        $other = $this->company('Lain');
        $manager = $this->user('manager', $c);
        $this->expectException(ValidationException::class);
        $this->project($manager, $manager, $c, [], [$this->division($other, 'Asing')]);
    }

    public function test_unique_members_are_not_double_counted_across_divisions(): void
    {
        $c = $this->company();
        $dev = $this->division($c, 'Development');
        $creative = $this->division($c, 'Creative');
        $manager = $this->user('manager', $c, [$dev]);
        $duplicate = $this->user('member', $c, [$dev, $creative]);
        $p = $this->project($manager, $manager, $c, [$duplicate, $duplicate], [$dev, $creative]);
        $h = ProjectTeamService::counts($p);
        $this->assertSame(2, $h['people']);
        $this->assertSame(2, $h['divisions']);
        $pm = DB::table('project_members')->where('project_id', $p->id)->where('user_id', $duplicate->id)->value('id');
        $this->assertSame(2, DB::table('project_member_divisions')->where('project_member_id', $pm)->count());
    }

    public function test_recurring_opens_new_period_without_closing_parent_and_old_tasks_stay(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'recurring', $this->template('recurring'));
        $period1 = $p->periods()->first();
        $this->assertNotNull($period1);
        $this->assertSame(5, Task::query()->where('project_period_id', $period1->id)->count());
        $period2 = app(ProjectTemplateService::class)->openPeriod($p, $manager);
        $this->assertNotSame($period1->period_code, $period2->period_code);
        $this->assertSame('closed', $period1->refresh()->status);
        $this->assertSame('active', $p->refresh()->status);
        $this->assertSame(5, Task::query()->where('project_period_id', $period1->id)->count());
        $this->assertSame(5, Task::query()->where('project_period_id', $period2->id)->count());
    }

    public function test_one_time_project_has_no_periods(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c);
        $this->assertSame(0, $p->periods()->count());
        $this->expectException(ValidationException::class);
        app(ProjectTemplateService::class)->openPeriod($p, $manager);
    }

    public function test_project_progress_excludes_subtasks_from_denominator(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        Task::query()->where('project_id', $p->id)->whereNull('parent_id')->where('title', 'Analisis')->update(['status' => 'done', 'completed_at' => now()]);
        Task::query()->where('project_id', $p->id)->whereNotNull('parent_id')->update(['status' => 'done']);
        $pr = ManagementMetricsService::projectProgress($p->id);
        $this->assertSame(['total' => 3, 'done' => 1, 'percent' => 33], $pr);
    }
}
