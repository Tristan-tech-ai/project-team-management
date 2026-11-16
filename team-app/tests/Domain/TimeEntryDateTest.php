<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Services\ProjectTeamService;
use App\Support\Dates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Regression: "Catat waktu kerja" sends today's date in the display time zone. Between 00.00 and 08.00 WITA the UTC date
 * is still yesterday, and the rule before_or_equal:today (UTC) refused the button with "Work date ... today".
 */
class TimeEntryDateTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_local_today_is_accepted_after_midnight_and_tomorrow_is_refused(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $member = $this->user('member', $c);
        $project = $this->project($manager, $manager, $c, [$member], [], 'one_time', $this->template());
        $task = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('sort_order')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($task, $member->id, [$member->id], $manager);

        $local = Carbon::parse('2026-11-16 00:30', Dates::zone());
        Carbon::setTestNow($local);
        try {
            $this->assertNotSame($local->toDateString(), $local->copy()->utc()->toDateString(), 'the UTC date is still yesterday at 00.30 WITA');
            $this->actingAs($member)->postJson(route('tasks.time.store', $task), ['minutes_spent' => 15, 'work_date' => '2026-11-16'])->assertOk();
            $this->actingAs($member)->postJson(route('tasks.time.store', $task), ['minutes_spent' => 15, 'work_date' => '2026-11-17'])
                ->assertStatus(422)->assertJsonPath('errors.work_date.0', 'Waktu kerja hanya bisa dicatat untuk hari ini atau tanggal sebelumnya.');
        } finally {
            Carbon::setTestNow();
        }
    }
}
