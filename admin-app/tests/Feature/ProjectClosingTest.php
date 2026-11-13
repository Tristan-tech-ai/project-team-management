<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Technical Guide 8.5: a one_time project closes after all main tasks are done and approved. */
class ProjectClosingTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_one_time_project_closes_only_after_all_main_tasks_are_done(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $payload = ['name' => $p->name, 'pic_user_id' => $manager->id, 'status' => 'completed', 'members' => [$manager->id]];

        $this->actingAs($manager)->put("/projects/{$p->id}", $payload)->assertRedirect()->assertSessionHas('error');
        $this->assertNotSame('completed', $p->refresh()->status);

        Task::query()->where('project_id', $p->id)->whereNull('parent_id')->update(['status' => 'done', 'completed_at' => now(), 'completed_by' => $manager->id]);
        $this->actingAs($manager)->put("/projects/{$p->id}", $payload)->assertRedirect(route('projects.show', $p));
        $p->refresh();
        $this->assertSame('completed', $p->status);
        $this->assertNotNull($p->completed_at);
    }
}
