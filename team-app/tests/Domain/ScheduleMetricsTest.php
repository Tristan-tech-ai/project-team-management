<?php

namespace Tests\Domain;

use App\Models\ProjectActivity;
use App\Models\Task;
use App\Services\ManagementMetricsService;
use App\Services\ScheduleChangeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** LV-04 and LV-05: deadline changes keep the baseline, activities do not shift deadlines, dynamic overdue, KPIs. */
class ScheduleMetricsTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_only_approved_requests_change_deadline_and_baseline_stays(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $task = Task::query()->where('project_id', $p->id)->where('title', 'Rilis')->firstOrFail();
        $baseline = $task->baseline_due_at->copy();

        $meeting = ProjectActivity::create(['project_id' => $p->id, 'task_id' => $task->id, 'created_by' => $member->id, 'activity_type' => 'meeting',
            'title' => 'Rapat klien tambahan', 'status' => 'done', 'occurred_at' => now(), 'duration_minutes' => 120, 'impact_type' => 'delay']);
        $this->assertTrue($task->refresh()->due_at->equalTo($baseline), 'an activity must not move the deadline automatically');

        $svc = app(ScheduleChangeService::class);
        $r = $svc->propose($p, $task, $meeting, now()->addDays(20), 'Klien minta revisi alur', $member);
        $this->assertSame('pending', $r->status);
        $this->assertTrue($task->refresh()->due_at->equalTo($baseline));
        // A second request for the same task is refused until the first one is decided.
        try {
            $svc->propose($p, $task, null, now()->addDays(25), 'Lagi', $member);
            $this->fail('a second request must be refused');
        } catch (ValidationException) {
        }
        // A Member cannot decide.
        try {
            $svc->decide($r, true, null, $member);
            $this->fail('a member must not be able to decide');
        } catch (ValidationException) {
        }
        $r = $svc->decide($r, true, null, $manager);
        $task->refresh();
        $this->assertSame('approved', $r->status);
        $this->assertNotNull($r->applied_at);
        $this->assertTrue($task->due_at->equalTo($r->proposed_due_at));
        $this->assertTrue($task->baseline_due_at->equalTo($baseline), 'baseline_due_at must not change');
        $this->assertTrue($r->previous_due_at->equalTo($baseline));
    }

    public function test_rejection_requires_reason_and_keeps_deadline(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c);
        $svc = app(ScheduleChangeService::class);
        $r = $svc->propose($p, null, null, now()->addDays(30), 'Tambah ruang lingkup', $manager);
        try {
            $svc->decide($r, false, '', $manager);
            $this->fail('a rejection without a reason must be refused');
        } catch (ValidationException) {
        }
        $due = $p->refresh()->due_at->copy();
        $r = $svc->decide($r, false, 'Ruang lingkup tetap', $manager);
        $this->assertSame('rejected', $r->status);
        $this->assertNull($r->applied_at);
        $this->assertTrue($p->refresh()->due_at->equalTo($due));
    }

    public function test_dynamic_overdue_and_kpi_separate_unique_members_from_assignments(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a], [], 'one_time', $this->template());
        $main = Task::query()->where('project_id', $p->id)->whereNull('parent_id')->get();
        foreach ($main as $t) {
            DB::table('task_assignees')->insertOrIgnore(['task_id' => $t->id, 'user_id' => $a->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        Task::query()->whereKey($main[0]->id)->update(['due_at' => now()->subDay()]);
        Task::query()->whereKey($main[1]->id)->update(['due_at' => now()->subDay(), 'status' => 'done', 'completed_at' => now()]);

        $k = app(ManagementMetricsService::class)->kpi($manager);
        $this->assertSame(1, Task::query()->where('project_id', $p->id)->whereNull('parent_id')->where('status', '!=', 'done')->where('due_at', '<', now())->count());
        $this->assertGreaterThanOrEqual(1, $k['overdue']);
        $rows = app(ManagementMetricsService::class)->monitoring($manager)->firstWhere('id', $p->id);
        $this->assertSame(2, (int) $rows->members);
        $this->assertSame(1, (int) $rows->overdue_tasks);
        $this->assertSame('delayed', $rows->condition);
        $this->assertSame(33, $rows->progress);
        $counts = \App\Services\ProjectTeamService::counts($p);
        $this->assertSame(2, $counts['people']);
        $this->assertSame(6, $counts['assignments']);
    }
}
