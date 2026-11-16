<?php

namespace Tests\Feature;

use App\Models\ScheduleChangeRequest;
use App\Models\Task;
use App\Services\TodoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/**
 * I3: the task detail opens as a side panel over the board, list, and dashboard. The panel loads GET /tasks/{id}?panel=1 and
 * sends its forms as JSON to the same endpoints, so the permission and status rules are the ones of the full page.
 */
class TaskPanelTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    /** @return array{0: \App\Models\User, 1: \App\Models\User, 2: Task} manager (project PIC), member (assignee), first main task */
    private function setUpTask(): array
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $project = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $task = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('sort_order')->firstOrFail();
        $this->actingAs($manager)->putJson(route('tasks.assignees', $task), ['pic_user_id' => $member->id, 'assignees' => [$member->id]])->assertOk();

        return [$manager, $member, $task->fresh()];
    }

    public function test_panel_returns_only_the_task_detail(): void
    {
        [$manager, , $task] = $this->setUpTask();
        $html = $this->actingAs($manager)->get(route('tasks.show', $task).'?panel=1')->assertOk()->getContent();
        $this->assertStringContainsString('data-task-panel-content', $html);
        $this->assertStringContainsString(e($task->title), $html);
        $this->assertStringNotContainsString('<html', $html, 'no layout around the panel content');
        $this->assertStringNotContainsString('admin-sidebar', $html);
        $this->assertStringContainsString('class="col-12"', $html, 'one column inside the panel');

        $page = $this->actingAs($manager)->get(route('tasks.show', $task))->assertOk()->getContent();
        $this->assertStringContainsString('id="task-panel"', $page, 'every page carries the panel');
        $this->assertStringContainsString('col-lg-8', $page, 'the full page keeps two columns');
    }

    public function test_panel_keeps_the_permissions_of_the_full_page(): void
    {
        [, , $task] = $this->setUpTask();
        $stranger = $this->user('member', $this->company('Company Lain'));
        $this->actingAs($stranger)->get(route('tasks.show', $task).'?panel=1')->assertForbidden();
    }

    public function test_board_list_and_todo_links_open_the_panel(): void
    {
        [$manager, $member, $task] = $this->setUpTask();
        $board = $this->actingAs($manager)->get(route('projects.board', $task->project_id))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/href="[^"]*\/tasks\/'.$task->id.'" class="task-title no-stretch" data-task-panel/', $board);
        $this->assertStringContainsString('data-refresh-region="board"', $board);

        $task->update(['due_at' => now()->addHours(5)]);
        $items = app(TodoService::class)->for($member);
        $this->assertNotEmpty($items);
        foreach ($items as $item) {
            $this->assertSame(str_contains($item['url'], '/tasks/'), $item['panel'], json_encode($item));
        }
    }

    public function test_panel_actions_answer_with_json(): void
    {
        [$manager, $member, $task] = $this->setUpTask();
        $this->actingAs($manager)->putJson(route('tasks.update', $task), ['title' => 'Analisis kebutuhan', 'priority' => 'high', 'task_type' => 'general'])
            ->assertOk()->assertJson(['message' => 'Task tersimpan.']);
        $this->actingAs($manager)->putJson(route('tasks.update', $task), ['title' => '', 'priority' => 'high', 'task_type' => 'general'])
            ->assertStatus(422)->assertJsonValidationErrors('title');
        $this->actingAs($member)->postJson(route('tasks.time.store', $task), ['minutes_spent' => 30, 'work_date' => now()->toDateString()])
            ->assertOk()->assertJsonPath('message', 'Waktu kerja tercatat: 30 menit.');
        $this->actingAs($member)->postJson(route('schedule-changes.store'), ['project_id' => $task->project_id, 'task_id' => $task->id,
            'proposed_due_at' => now()->addDays(20)->format('Y-m-d\TH:i'), 'reason' => 'Menunggu materi dari klien'])->assertOk();
        $admin = $this->user('super_admin'); // deleting a task is a Super Admin permission in the role seed
        $this->actingAs($admin)->deleteJson(route('tasks.destroy', $task))->assertOk()->assertJson(['deleted' => true]);
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        // Plain forms (no JavaScript) still get a redirect with the message.
        $other = Task::query()->where('project_id', $task->project_id)->whereNull('parent_id')->where('id', '!=', $task->id)->firstOrFail();
        $this->actingAs($manager)->from(route('tasks.show', $other))->put(route('tasks.update', $other), ['title' => 'Desain halaman', 'priority' => 'normal', 'task_type' => 'design'])
            ->assertRedirect(route('tasks.show', $other))->assertSessionHas('ok', 'Task tersimpan.');
    }

    /** A deadline shift is offered from the current deadline (+3/+7/+14 days), not from today. */
    public function test_deadline_request_offers_shifts_from_the_current_deadline(): void
    {
        [$manager, , $task] = $this->setUpTask();
        $task->update(['due_at' => \App\Support\Dates::fromInput('2026-12-01T17:00'), 'baseline_due_at' => \App\Support\Dates::fromInput('2026-12-01T17:00')]);
        $html = $this->actingAs($manager)->get(route('tasks.show', $task).'?panel=1')->assertOk()->getContent();
        $this->assertStringContainsString('data-base-project="2026-12-01T17:00"', $html);
        foreach ([3, 7, 14] as $days) {
            $this->assertStringContainsString('data-fill-days="'.$days.'" data-target="#f-proposed_due_at"', $html);
        }
    }

    /** "Putuskan geser deadline" in Perlu kamu kerjakan opens the task, so the decision has to be possible there. */
    public function test_deadline_request_is_decided_on_the_task(): void
    {
        [$manager, $member, $task] = $this->setUpTask();
        $this->actingAs($member)->post(route('schedule-changes.store'), ['project_id' => $task->project_id, 'task_id' => $task->id,
            'proposed_due_at' => now()->addDays(20)->format('Y-m-d\TH:i'), 'reason' => 'Menunggu materi dari klien']);
        $request = ScheduleChangeRequest::query()->where('task_id', $task->id)->where('status', 'pending')->firstOrFail();
        $decision = route('schedule-changes.decision', $request);

        $this->assertStringContainsString($decision, $this->actingAs($manager)->get(route('tasks.show', $task).'?panel=1')->getContent());
        $this->assertStringNotContainsString($decision, $this->actingAs($member)->get(route('tasks.show', $task))->getContent(), 'the requester does not decide');
        $todo = collect(app(TodoService::class)->for($manager))->firstWhere('kind', 'schedule');
        $this->assertStringEndsWith('/tasks/'.$task->id.'#schedule', $todo['url']);

        $this->actingAs($manager)->postJson($decision, ['decision' => 'rejected'])->assertStatus(422)->assertJsonValidationErrors('decision_note');
        $this->actingAs($manager)->postJson($decision, ['decision' => 'approved'])->assertOk()->assertJson(['status' => 'approved']);
        $this->assertSame($request->fresh()->proposed_due_at->toDateTimeString(), $task->fresh()->due_at->toDateTimeString());
    }
}
