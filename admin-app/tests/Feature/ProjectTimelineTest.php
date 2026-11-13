<?php

namespace Tests\Feature;

use App\Models\ProjectActivity;
use App\Models\Task;
use App\Services\ProjectTeamService;
use App\Services\ScheduleChangeService;
use App\Services\TaskWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Technical Guide 7.3 prototype: one timeline with finished tasks, activities, and deadline requests, plus the schedule condition card. */
class ProjectTimelineTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_timeline_merges_finished_tasks_activities_and_deadline_requests_in_order(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $analysis = Task::query()->where('project_id', $p->id)->where('title', 'Analisis')->firstOrFail();
        $release = Task::query()->where('project_id', $p->id)->where('title', 'Rilis')->firstOrFail();

        app(ProjectTeamService::class)->setAssignees($analysis, $member->id, [$member->id], $manager);
        $workflow = app(TaskWorkflowService::class);
        $workflow->move($analysis->refresh(), 'in_progress', $member);
        $submission = $workflow->submitResult($analysis->refresh(), $member, 'https://example.test/result', null, 'Siap');
        $workflow->decide($submission, 'approved', $manager, null);
        Task::query()->whereKey($analysis->id)->update(['completed_at' => now()->subDays(3)]);

        $meeting = ProjectActivity::create(['project_id' => $p->id, 'task_id' => $release->id, 'created_by' => $member->id, 'activity_type' => 'meeting',
            'title' => 'Rapat tambahan dengan klien', 'status' => 'done', 'occurred_at' => now()->subDays(2), 'duration_minutes' => 120, 'impact_type' => 'delay']);
        ProjectActivity::create(['project_id' => $p->id, 'created_by' => $member->id, 'activity_type' => 'visit',
            'title' => 'Kunjungan klien', 'status' => 'done', 'occurred_at' => now()->subDay(), 'impact_type' => 'none']);
        app(ScheduleChangeService::class)->propose($p, $release, $meeting, now()->addDays(30), 'Klien menambah kanal', $member);

        $html = $this->actingAs($manager)->get("/projects/{$p->id}/activities")->assertOk()->getContent();
        $order = array_map(fn ($text) => strpos($html, $text), ['Task: <a', 'Rapat tambahan dengan klien', 'Kunjungan klien', 'Usulan perubahan deadline']);
        $this->assertNotContains(false, $order, 'every kind of event must be on the timeline');
        $this->assertSame($order, array_values(collect($order)->sort()->all()), 'the timeline is chronological like the prototype');
        $this->assertStringContainsString('Task selesai dan telah diterima '.$manager->name, $html);
        $this->assertStringContainsString('Kondisi Jadwal', $html);
        $this->assertStringContainsString('Menunggu persetujuan', $html);
        $this->assertStringContainsString('Setujui deadline baru', $html);
    }

    public function test_without_page_parameter_the_newest_page_opens(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c);
        foreach (range(1, 23) as $i) {
            ProjectActivity::create(['project_id' => $p->id, 'created_by' => $manager->id, 'activity_type' => 'meeting',
                'title' => sprintf('Rapat ke-%02d', $i), 'status' => 'done', 'occurred_at' => now()->subDays(30 - $i), 'impact_type' => 'none']);
        }
        $last = $this->actingAs($manager)->get("/projects/{$p->id}/activities")->assertOk()->getContent();
        $this->assertStringContainsString('Rapat ke-23', $last);
        $this->assertStringNotContainsString('Rapat ke-01', $last);
        $first = $this->actingAs($manager)->get("/projects/{$p->id}/activities?page=1")->assertOk()->getContent();
        $this->assertStringContainsString('Rapat ke-01', $first);
        $this->assertStringNotContainsString('Rapat ke-23', $first);
    }
}
