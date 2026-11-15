<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Services\ProjectTeamService;
use App\Services\ScheduleChangeService;
use App\Services\TaskWorkflowService;
use App\Services\TodoService;
use App\Support\Dates;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** I1 "Perlu kamu kerjakan" and I4 readable dates. */
class TodoTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_relative_dates_read_like_people_talk(): void
    {
        Dates::forget();
        $now = Carbon::parse('2026-11-15 09:00', Dates::zone());
        $this->assertSame('hari ini 17.00', Dates::relative(Carbon::parse('2026-11-15 17:00', Dates::zone()), now: $now));
        $this->assertSame('besok 09.30', Dates::relative(Carbon::parse('2026-11-16 09:30', Dates::zone()), now: $now));
        $this->assertSame('5 hari lagi', Dates::relative(Carbon::parse('2026-11-20 08:00', Dates::zone()), now: $now));
        $this->assertSame('terlambat 3 jam', Dates::relative(Carbon::parse('2026-11-15 06:00', Dates::zone()), now: $now));
        $this->assertSame('terlambat 2 hari', Dates::relative(Carbon::parse('2026-11-13 10:00', Dates::zone()), now: $now));
        $this->assertSame('baru saja', Dates::relative(Carbon::parse('2026-11-15 08:59', Dates::zone()), past: true, now: $now));
        $this->assertSame('2 jam lalu', Dates::relative(Carbon::parse('2026-11-15 07:00', Dates::zone()), past: true, now: $now));
        $this->assertSame('kemarin', Dates::relative(Carbon::parse('2026-11-14 20:00', Dates::zone()), past: true, now: $now));
        $this->assertNull(Dates::relative(null));
    }

    public function test_each_role_sees_what_needs_them(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $p = $this->project($manager, $manager, $c, [$a], [], 'one_time', $this->template());
        $design = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        $release = Task::query()->where('project_id', $p->id)->where('title', 'Rilis')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($design, $a->id, [$a->id], $manager);
        app(ProjectTeamService::class)->setAssignees($release, $a->id, [$a->id], $manager);
        $release->update(['due_at' => now()->subDay()]);
        $wf = app(TaskWorkflowService::class);
        $design = $wf->move($design->refresh(), 'in_progress', $a);
        $sub = $wf->submitResult($design, $a, 'https://contoh.test/hasil', null, 'Versi 1');
        app(ScheduleChangeService::class)->propose($p, $release, null, now()->addDays(10), 'Klien terlambat kirim bahan', $a);

        $forManager = collect(app(TodoService::class)->for($manager));
        // A manager of the same company who is not the project PIC also reviews (regression: the project was loaded without company_id).
        $otherManager = $this->user('manager', $c);
        $this->assertTrue(collect(app(TodoService::class)->for($otherManager))->contains(fn ($i) => $i['kind'] === 'review'), 'company manager sees the review too');
        $this->assertTrue($forManager->contains(fn ($i) => $i['kind'] === 'review' && str_contains($i['title'], 'Desain')), 'the reviewer sees the result to review');
        $this->assertTrue($forManager->contains(fn ($i) => $i['kind'] === 'schedule'), 'the manager sees the deadline request');

        $forMember = collect(app(TodoService::class)->for($a));
        $this->assertFalse($forMember->contains(fn ($i) => $i['kind'] === 'review'), 'a member does not review');
        $this->assertFalse($forMember->contains(fn ($i) => $i['kind'] === 'schedule'), 'nobody decides their own request');
        $this->assertTrue($forMember->contains(fn ($i) => $i['kind'] === 'late' && str_contains($i['title'], 'Rilis') && str_starts_with($i['when'], 'terlambat')));

        $wf->decide($sub->refresh(), 'revision', $manager, 'Tambahkan versi HP');
        $forMember = collect(app(TodoService::class)->for($a));
        $this->assertTrue($forMember->contains(fn ($i) => $i['kind'] === 'revision' && $i['title'] === 'Perbaiki Desain'));
        $this->assertFalse(collect(app(TodoService::class)->for($manager))->contains(fn ($i) => $i['kind'] === 'review'), 'a decided result leaves the list');
    }
}
