<?php

namespace Tests\Domain;

use App\Models\ProjectActivity;
use App\Models\Task;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** LV-04: the calendar separates deadlines, activities, publications, and actual completion; it never uses updated_at. Same endpoint in both apps. */
class CalendarTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_calendar_modes_are_separate(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c);
        $start = now()->startOfMonth();
        $deadline = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Deadline saja',
            'status' => 'in_progress', 'due_at' => $start->copy()->addDays(10)]);
        $contentTask = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Feed promo',
            'status' => 'backlog', 'task_type' => 'content', 'platform' => 'Instagram', 'scheduled_publish_at' => $start->copy()->addDays(12), 'publication_status' => 'scheduled']);
        $done = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Sudah beres',
            'status' => 'done', 'completed_at' => $start->copy()->addDays(3), 'completed_by' => $manager->id]);
        // updated_at is deliberately inside the range and completed_at is not: the task must not appear in Done mode for a range covering updated_at only.
        $stale = Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Diubah saja', 'status' => 'in_progress']);
        ProjectActivity::create(['project_id' => $p->id, 'created_by' => $manager->id, 'activity_type' => 'visit', 'title' => 'Kunjungan klien',
            'status' => 'done', 'occurred_at' => $start->copy()->addDays(5), 'duration_minutes' => 90, 'impact_type' => 'none']);
        $range = ['start' => $start->copy()->subDay()->toIso8601String(), 'end' => $start->copy()->addMonth()->toIso8601String(), 'project_id' => $p->id];
        $title = fn ($mode) => collect($this->actingAs($manager)->getJson('/calendar/events?'.http_build_query($range + ['mode' => $mode]))->assertOk()->json())->pluck('title')->all();

        $this->assertSame(['Deadline: Deadline saja'], $title('schedule'));
        $this->assertSame(['Jadwal terbit: Feed promo (Instagram)'], $title('publication'));
        $this->assertSame(['Selesai: Sudah beres'], $title('done'));
        $this->assertSame(['Kunjungan: Kunjungan klien'], $title('activities'));
        $this->assertNotContains('Selesai: Diubah saja', $title('done'));
    }

    public function test_calendar_only_shows_projects_in_scope(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c);
        Task::create(['project_id' => $p->id, 'pic_user_id' => $manager->id, 'created_by' => $manager->id, 'title' => 'Rahasia', 'status' => 'backlog', 'due_at' => now()->addDay()]);
        $ev = $this->actingAs($member)->getJson('/calendar/events?'.http_build_query(['mode' => 'schedule', 'start' => now()->subWeek()->toIso8601String(), 'end' => now()->addWeek()->toIso8601String()]))->assertOk()->json();
        $this->assertSame([], $ev);
    }
}
