<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Services\ProjectTeamService;
use App\Support\ProjectPeople;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** I5: the people picker shows how many open main tasks each person holds (as PIC or assignee, counted once, done tasks excluded). */
class WorkloadTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_open_tasks_are_counted_once_per_person(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $b = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a, $b], [], 'one_time', $this->template());
        $tasks = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->orderBy('id')->get();
        $team = app(ProjectTeamService::class);
        $team->setAssignees($tasks[0], $a->id, [$a->id, $b->id], $manager);
        $team->setAssignees($tasks[1], $a->id, [$a->id], $manager);
        $team->setAssignees($tasks[2], $b->id, [$b->id], $manager);
        $tasks[2]->update(['status' => 'done', 'completed_at' => now()]);

        $load = ProjectPeople::openTasks([$a->id, $b->id]);
        $this->assertSame(2, $load[$a->id], 'PIC and assignee of the same task count once');
        $this->assertSame(1, $load[$b->id], 'a done task is not counted');

        $people = collect(ProjectPeople::byProject([$p->id])[$p->id]);
        $this->assertSame(2, $people->firstWhere('id', $a->id)['open']);
        $this->assertSame([], ProjectPeople::openTasks([]));
    }
}
