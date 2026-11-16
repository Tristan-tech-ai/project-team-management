<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Task;
use App\Services\ProjectTeamService;
use App\Support\AuditSentence;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** I13: bulk actions on the task list. Every task keeps its own permission and status rules; what cannot change is skipped with the reason. */
class TaskBulkTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    /** @return array{0: \App\Models\User, 1: \App\Models\User, 2: \App\Models\User, 3: \Illuminate\Support\Collection<int, Task>} */
    private function setUpProject(): array
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $ayu = $this->user('member', $c);
        $budi = $this->user('member', $c);
        $project = $this->project($manager, $manager, $c, [$ayu, $budi], [], 'one_time', $this->template());
        $tasks = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('sort_order')->get();
        foreach ($tasks as $t) {
            app(ProjectTeamService::class)->setAssignees($t, $ayu->id, [$ayu->id], $manager);
        }

        return [$manager, $ayu, $budi, $tasks->map->fresh()];
    }

    public function test_start_runs_the_status_rules_per_task(): void
    {
        [$manager, , , $tasks] = $this->setUpProject();
        $tasks[1]->update(['status' => 'in_progress']);
        $foreign = $this->setUpProject()[3][0]; // a task in another company, outside this manager's scope

        $r = $this->actingAs($manager)->postJson(route('tasks.bulk'), ['ids' => [$tasks[0]->id, $tasks[1]->id, $foreign->id], 'action' => 'start'])
            ->assertOk()->json();
        $this->assertSame(1, $r['done']);
        $this->assertSame('1 task diperbarui. 2 dilewati.', $r['message']);
        $this->assertSame([$tasks[1]->title.': sudah tidak di Antrian', 'Task #'.$foreign->id.': tidak ditemukan atau di luar aksesmu'],
            array_map(fn ($s) => $s['title'].': '.$s['reason'], $r['skipped']));
        $this->assertSame('in_progress', $tasks[0]->fresh()->status);
        $this->assertSame('backlog', $foreign->fresh()->status);
    }

    public function test_change_pic_keeps_the_previous_pic_and_requires_project_members(): void
    {
        [$manager, $ayu, $budi, $tasks] = $this->setUpProject();
        $outsider = $this->user('member', $this->company('Company Lain'));

        $this->actingAs($manager)->postJson(route('tasks.bulk'), ['ids' => [$tasks[0]->id, $tasks[2]->id], 'action' => 'pic', 'user_id' => $budi->id])
            ->assertOk()->assertJson(['done' => 2, 'skipped' => []]);
        $this->assertSame($budi->id, (int) $tasks[0]->fresh()->pic_user_id);
        $this->assertEqualsCanonicalizing([$ayu->id, $budi->id], $tasks[0]->fresh()->assignees->pluck('id')->all(), 'the previous PIC stays an assignee');

        $r = $this->actingAs($manager)->postJson(route('tasks.bulk'), ['ids' => [$tasks[1]->id], 'action' => 'pic', 'user_id' => $outsider->id])->assertOk()->json();
        $this->assertSame(0, $r['done']);
        $this->assertStringContainsString('bukan anggota project', $r['skipped'][0]['reason']);

        $this->actingAs($manager)->postJson(route('tasks.bulk'), ['ids' => [$tasks[1]->id], 'action' => 'pic'])
            ->assertStatus(422)->assertJsonPath('errors.user_id.0', 'Pilih orangnya dulu.');
    }

    public function test_add_assignee_skips_people_already_on_the_task(): void
    {
        [$manager, $ayu, $budi, $tasks] = $this->setUpProject();
        $r = $this->actingAs($manager)->postJson(route('tasks.bulk'), ['ids' => $tasks->pluck('id')->all(), 'action' => 'assignee', 'user_id' => $budi->id])->assertOk()->json();
        $this->assertSame(3, $r['done']);
        $this->assertTrue($tasks[2]->fresh()->assignees->contains('id', $budi->id));
        $this->assertSame($ayu->id, (int) $tasks[2]->fresh()->pic_user_id, 'adding an assignee does not change the PIC');

        $again = $this->actingAs($manager)->postJson(route('tasks.bulk'), ['ids' => [$tasks[0]->id], 'action' => 'assignee', 'user_id' => $budi->id])->json();
        $this->assertSame(0, $again['done']);
        $this->assertStringContainsString('sudah pelaksana', $again['skipped'][0]['reason']);
    }

    /** The member role (SQL seed) may move status but not assign: in bulk a member can start tasks, not reassign them. */
    public function test_a_member_cannot_reassign_tasks_in_bulk(): void
    {
        [, $ayu, $budi, $tasks] = $this->setUpProject();
        $r = $this->actingAs($ayu)->postJson(route('tasks.bulk'), ['ids' => [$tasks[0]->id], 'action' => 'pic', 'user_id' => $budi->id])->assertOk()->json();
        $this->assertSame(0, $r['done']);
        $this->assertSame('kamu tidak berizin mengatur pelaksana task ini', $r['skipped'][0]['reason']);
        $this->assertSame($ayu->id, (int) $tasks[0]->fresh()->pic_user_id);
        $this->actingAs($ayu)->postJson(route('tasks.bulk'), ['ids' => [$tasks[0]->id], 'action' => 'start'])->assertOk()->assertJson(['done' => 1]);
    }

    public function test_lists_offer_the_bulk_bar_only_with_permission(): void
    {
        [$manager, $ayu, , $tasks] = $this->setUpProject();
        $page = $this->actingAs($manager)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-bulk-form', $page);
        $this->assertStringContainsString('data-bulk-all', $page);
        $this->assertStringContainsString('&quot;render&quot;:&quot;select&quot;', $page);
        $this->assertStringContainsString('<option value="pic">Ganti PIC</option>', $page);
        $project = $this->actingAs($manager)->get(route('projects.tasks', $tasks[0]->project_id))->assertOk()->getContent();
        $this->assertStringContainsString('data-table="#project-task-table"', $project);
        $this->assertStringContainsString(e($ayu->name).' (3 task aktif)', $project, 'project members are offered with their open tasks');

        $member = $this->actingAs($ayu)->get(route('tasks.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="start">Mulai kerjakan</option>', $member);
        $this->assertStringNotContainsString('value="pic"', $member, 'only the actions the role allows');

        $feed = $this->actingAs($ayu)->getJson(route('tasks.data').'?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => 10,
            'columns' => [['data' => 'bulk', 'name' => 'bulk', 'searchable' => 'false', 'orderable' => 'false', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']]],
            'order' => [['column' => 1, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false']]))->assertOk()->json('data');
        $this->assertEqualsCanonicalizing($tasks->pluck('id')->all(), array_column($feed, 'bulk'), 'her own tasks can be ticked');
    }

    public function test_activity_log_reads_as_a_sentence(): void
    {
        $log = new AuditLog(['route' => 'tasks.bulk', 'input' => ['action' => 'pic', 'ids' => [1, 2, 3], 'user_id' => 4]]);
        $this->assertSame('mengganti PIC 3 task sekaligus', AuditSentence::describe($log));
    }
}
