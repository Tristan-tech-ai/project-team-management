<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Models\TaskActivity;
use App\Services\ProjectTeamService;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** LV-03: status, multiple assignees, checklist, review, revision with a reason. Runs identically in admin-app and team-app. */
class TaskWorkflowTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    private function prepare(): array
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $b = $this->user('member', $c);
        $outsider = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a, $b, $outsider], [], 'one_time', $this->template());
        $task = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($task, $a->id, [$a->id, $b->id], $manager);

        return [$manager, $a, $b, $outsider, $task->refresh(), $p];
    }

    private function wf(): TaskWorkflowService
    {
        return app(TaskWorkflowService::class);
    }

    public function test_task_with_two_assignees_includes_pic(): void
    {
        [$manager, $a, $b, , $task] = $this->prepare();
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $task->assignees()->pluck('users.id')->all());
        $this->assertSame($a->id, $task->pic_user_id);
        $this->assertTrue($a->can('moveStatus', $task));
        $this->assertTrue($b->can('moveStatus', $task));
    }

    public function test_assignees_must_be_project_members(): void
    {
        [$manager, $a, , , $task] = $this->prepare();
        $foreign = $this->user('member', $this->company('Lain'));
        $this->expectException(ValidationException::class);
        app(ProjectTeamService::class)->setAssignees($task, $a->id, [$foreign->id], $manager);
    }

    public function test_non_assignee_member_cannot_move_status(): void
    {
        [, , , $outsider, $task] = $this->prepare();
        $this->expectException(ValidationException::class);
        $this->wf()->move($task, 'in_progress', $outsider);
    }

    public function test_flow_start_submit_revise_then_approve(): void
    {
        [$manager, $a, $b, , $task] = $this->prepare();
        $t = $this->wf()->move($task, 'in_progress', $b);
        $this->assertSame('in_progress', $t->status);
        $this->assertNotNull($t->start_at);

        // Members cannot skip Review: moving straight to review or done is refused.
        foreach (['review', 'done'] as $to) {
            try {
                $this->wf()->move($t, $to, $a);
                $this->fail("moving to {$to} must be refused");
            } catch (ValidationException) {
            }
        }

        $sub = $this->wf()->submitResult($t, $a, 'https://contoh.test/hasil-desain', null, 'Versi pertama');
        $this->assertSame('review', $t->refresh()->status);
        $this->assertSame(1, $sub->attachments()->count());

        // A Member assignee cannot approve their own result.
        try {
            $this->wf()->decide($sub, 'approved', $a, null);
            $this->fail('a member must not be able to approve a result');
        } catch (ValidationException) {
        }
        // A revision requires a reason.
        try {
            $this->wf()->decide($sub, 'revision', $manager, '');
            $this->fail('a revision without a reason must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('review_note', $e->errors());
        }
        $t = $this->wf()->decide($sub, 'revision', $manager, 'Warna tombol belum sesuai brand');
        $this->assertSame('in_progress', $t->status);
        $this->assertSame('revision', $sub->refresh()->status);

        $sub2 = $this->wf()->submitResult($t, $b, 'https://contoh.test/hasil-desain-2', null, 'Sudah diperbaiki');
        $t = $this->wf()->decide($sub2, 'approved', $manager, null);
        $this->assertSame('done', $t->status);
        $this->assertNotNull($t->completed_at);
        $this->assertSame($manager->id, $t->completed_by);
        // Submission history is never deleted.
        $this->assertSame(2, $t->submissions()->count());
        $this->assertGreaterThanOrEqual(4, TaskActivity::query()->where('task_id', $t->id)->count());
    }

    public function test_reopening_clears_completed_fields(): void
    {
        [$manager, $a, , , $task] = $this->prepare();
        $t = $this->wf()->move($task, 'in_progress', $a);
        $sub = $this->wf()->submitResult($t, $a, 'https://contoh.test/x', null, null);
        $t = $this->wf()->decide($sub, 'approved', $manager, null);
        $t = $this->wf()->move($t, 'in_progress', $manager);
        $this->assertNull($t->completed_at);
        $this->assertNull($t->completed_by);
    }

    public function test_submission_requires_link_or_file(): void
    {
        [, $a, , , $task] = $this->prepare();
        $this->expectException(ValidationException::class);
        $this->wf()->submitResult($task, $a, null, null, 'tanpa bukti');
    }

    public function test_checklist_is_one_level_and_tracks_progress(): void
    {
        [, $a, , $outsider, $task] = $this->prepare();
        $subtaskList = $task->subtasks()->orderBy('sort_order')->get();
        $s = $this->wf()->toggleSubtask($subtaskList[0], $a, true);
        $this->assertSame('done', $s->status);
        $this->assertNotNull($s->completed_at);
        $this->assertSame(50, TaskWorkflowService::taskProgress($task->subtasks()->where('status', 'done')->count(), $task->subtasks()->count()));
        $s = $this->wf()->toggleSubtask($subtaskList[0], $a, false);
        $this->assertNull($s->completed_at);
        // Non-assignees cannot tick items; subtasks cannot be moved between columns.
        try {
            $this->wf()->toggleSubtask($subtaskList[1], $outsider, true);
            $this->fail('a non-assignee must be refused');
        } catch (ValidationException) {
        }
        $this->expectException(ValidationException::class);
        $this->wf()->move($subtaskList[1], 'in_progress', $a);
    }
}
