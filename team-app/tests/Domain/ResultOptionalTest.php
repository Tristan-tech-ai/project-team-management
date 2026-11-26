<?php

namespace Tests\Domain;

use App\Models\ProjectSetting;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Services\ProjectTeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Hasil task boleh kosong" (PKL supervisor, 26 Nov 2026): in a project with this setting a task is sent for review without a
 * link, file, or note (maintenance, research). Other projects still need a link or a file.
 */
class ResultOptionalTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    private function startedTask(bool $optional): array
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        if ($optional) {
            ProjectSetting::query()->create(['project_id' => $p->id, 'result_optional' => true]);
        }
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($t, $member->id, [$member->id], $manager);
        $this->actingAs($member)->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk();

        return [$t, $member];
    }

    public function test_empty_result_is_refused_by_default(): void
    {
        [$t, $member] = $this->startedTask(false);
        $this->actingAs($member)->post("/tasks/{$t->id}/submissions", ['external_url' => '', 'submission_note' => ''])->assertSessionHasErrors('external_url');
        $this->assertSame('in_progress', $t->fresh()->status);
    }

    public function test_project_with_optional_result_accepts_an_empty_submission(): void
    {
        [$t, $member] = $this->startedTask(true);
        $this->actingAs($member)->get("/tasks/{$t->id}")->assertOk()->assertSee('Di project ini hasil boleh kosong');
        $this->actingAs($member)->post("/tasks/{$t->id}/submissions", ['external_url' => '', 'submission_note' => ''])->assertSessionHasNoErrors();

        $this->assertSame('review', $t->fresh()->status, 'the task still goes to Review so the PIC knows it is done');
        $sub = TaskSubmission::query()->where('task_id', $t->id)->firstOrFail();
        $this->assertSame(0, $sub->attachments()->count());
        $this->actingAs($member)->get("/tasks/{$t->id}")->assertSee('Dikirim tanpa tautan, berkas, atau catatan.');
    }

    public function test_kanban_move_to_review_follows_the_setting(): void
    {
        [$t, $member] = $this->startedTask(false);
        $this->actingAs($member)->patchJson("/tasks/{$t->id}/status", ['status' => 'review'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('in_progress', $t->fresh()->status);

        [$t2, $member2] = $this->startedTask(true);
        $this->actingAs($member2)->patchJson("/tasks/{$t2->id}/status", ['status' => 'review'])->assertOk();
        $this->assertSame('review', $t2->fresh()->status, 'dragging the card to Review is the same as sending an empty result');
        $this->assertSame(1, TaskSubmission::query()->where('task_id', $t2->id)->where('status', 'pending')->count());
    }
}
