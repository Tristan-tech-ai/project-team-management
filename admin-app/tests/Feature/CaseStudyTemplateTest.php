<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Services\ManagementMetricsService;
use App\Services\TodoService;
use App\Support\Dates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/**
 * Case study 19 Nov (notes from the field): "Digital marketing (repeat)": content calendar on the 1st, Konten 1 to 5, insight
 * monitoring every day 10-15, social media admin every day, content report on the 28th; and "Web PPDB Sekolah" with feature
 * groups and their items, ending with Debug and QC.
 */
class CaseStudyTemplateTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    private const ROWS = "Kalender konten | 0\nKonten 1 | 4 | Upload konten 1\nKonten 2 | 9 | Upload konten 2\n"
        ."Pengawasan insight sosmed | | | daily 10:00-15:00\nAdmin sosmed | | | weekdays\nLaporan konten | 27";

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function monthlyTemplate($admin): ProjectTemplate
    {
        $this->actingAs($admin)->post(route('templates.projects.store'), ['name' => 'Digital Marketing Bulanan Uji', 'project_type' => 'recurring',
            'rows' => self::ROWS, 'is_active' => 1])->assertRedirect(route('templates.projects.index'));

        return ProjectTemplate::query()->where('name', 'Digital Marketing Bulanan Uji')->firstOrFail();
    }

    public function test_template_keeps_dates_and_routines(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $template = $this->monthlyTemplate($admin);
        $insight = $template->tasks()->where('title', 'Pengawasan insight sosmed')->firstOrFail();
        $this->assertSame(['daily', '10:00:00', '15:00:00'], [$insight->routine->repeat_rule, $insight->routine->start_time, $insight->routine->end_time]);
        $this->assertSame('weekdays', $template->tasks()->where('title', 'Admin sosmed')->firstOrFail()->routine->repeat_rule);
        // The edit form gives the same rows back, so saving it again changes nothing.
        $this->actingAs($admin)->get(route('templates.projects.edit', $template))->assertOk()
            ->assertSee('Pengawasan insight sosmed |  |  | daily 10:00-15:00', false)->assertSee('Admin sosmed |  |  | weekdays', false)->assertSee('Konten 1 | 4 | Upload konten 1', false);

        $this->actingAs($admin)->post(route('templates.projects.store'), ['name' => 'Salah', 'project_type' => 'recurring', 'rows' => "Admin | | | daily 15:00-10:00"])
            ->assertSessionHasErrors('rows');
        $this->actingAs($admin)->post(route('templates.projects.store'), ['name' => 'Salah 2', 'project_type' => 'recurring', 'rows' => "Admin | | | tiap hari"])
            ->assertSessionHasErrors('rows');
    }

    public function test_monthly_project_gets_dates_of_the_month_and_one_item_per_day(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $manager = $this->user('manager', $c);
        $putri = $this->user('member', $c);
        $template = $this->monthlyTemplate($admin);
        $zone = Dates::zone();
        Carbon::setTestNow(Carbon::parse('2026-12-10 09:00', $zone));

        $this->actingAs($manager)->post(route('projects.store'), ['company_id' => $c->id, 'name' => 'Digital Marketing PPDB', 'project_type' => 'recurring',
            'start_at' => '2026-12-01T08:00', 'recurrence_unit' => 'month', 'recurrence_interval' => 1, 'pic_user_id' => $manager->id, 'project_template_id' => $template->id,
            'members' => [$putri->id]])->assertRedirect();
        $project = Project::query()->where('name', 'Digital Marketing PPDB')->firstOrFail();
        $task = fn (string $title) => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('title', $title)->firstOrFail();

        // Deadlines are the end of the day in local time: tgl 1, tgl 5, tgl 28 of December 2026.
        $this->assertSame('2026-12-01 23:59', Dates::local($task('Kalender konten')->due_at)->format('Y-m-d H:i'));
        $this->assertSame('2026-12-05 23:59', Dates::local($task('Konten 1')->due_at)->format('Y-m-d H:i'));
        $this->assertSame('2026-12-28 23:59', Dates::local($task('Laporan konten')->due_at)->format('Y-m-d H:i'));
        $this->assertSame(['Upload konten 1'], $task('Konten 1')->subtasks()->pluck('title')->all());

        // Every day of December, 10.00 to 15.00; Monday to Friday only for the admin routine (23 weekdays in December 2026).
        $insight = $task('Pengawasan insight sosmed');
        $days = $insight->subtasks()->orderBy('sort_order')->get();
        $this->assertCount(31, $days);
        $this->assertSame('Selasa, 1 Des 10.00-15.00', $days[0]->title);
        $this->assertSame(['2026-12-01 10:00', '2026-12-01 15:00'], [Dates::local($days[0]->start_at)->format('Y-m-d H:i'), Dates::local($days[0]->due_at)->format('Y-m-d H:i')]);
        $this->assertSame('2026-12-31 15:00', Dates::local($insight->due_at)->format('Y-m-d H:i'), 'the routine ends with its last day');
        $admin = $task('Admin sosmed')->subtasks()->orderBy('sort_order')->get();
        $this->assertCount(23, $admin);
        $this->assertSame('Jumat, 4 Des', $admin[3]->title);
        $this->assertSame('Senin, 7 Des', $admin[4]->title);

        // Today (10 December) is first in the task detail and in "Perlu kamu kerjakan" until it is ticked.
        $html = $this->actingAs($manager)->get(route('tasks.show', $insight))->assertOk()->getContent();
        $this->assertStringContainsString('Checklist harian', $html);
        $this->assertMatchesRegularExpression('/routine-today.*?Kamis, 10 Des 10\.00-15\.00/s', $html);
        $this->assertStringContainsString('Belum dicentang (9 hari)', $html);
        $todo = collect(app(TodoService::class)->for($manager, 20));
        $this->assertNotNull($todo->firstWhere('title', 'Rutin hari ini: Pengawasan insight sosmed'));
        $today = $days->firstWhere('title', 'Kamis, 10 Des 10.00-15.00');
        $this->actingAs($manager)->patchJson(route('subtasks.toggle', $today), ['done' => true])->assertOk()->assertJsonFragment(['message' => 'Checklist selesai. Task kini Dikerjakan.']);
        $this->assertSame('in_progress', $insight->refresh()->status, 'ticking the first item starts the task');
        $this->assertNull(collect(app(TodoService::class)->for($manager, 20))->firstWhere('title', 'Rutin hari ini: Pengawasan insight sosmed'));

        // KPI: a ticked day counts at once; days count as passed once their time is over (insight until 15.00, admin until 23.59).
        $row = app(ManagementMetricsService::class)->memberWorkload($manager)->firstWhere('id', $manager->id);
        $this->assertSame([1, 9 + 1 + 7], [(int) $row->routine_done, (int) $row->routine_due], 'at 09.00: 9 insight days passed + today ticked, 7 admin weekdays passed');
        Carbon::setTestNow(Carbon::parse('2026-12-10 16:00', $zone));
        $row = app(ManagementMetricsService::class)->memberWorkload($manager)->firstWhere('id', $manager->id);
        $this->assertSame(1, (int) $row->routine_done);
        $this->assertSame(10 + 7, (int) $row->routine_due, '10 insight days (until 15.00) and 7 admin weekdays (1-4, 7-9 Dec; today ends at 23.59) have passed');

        // The board counts a routine in days.
        $this->actingAs($manager)->get(route('projects.board', $project))->assertOk()->assertSee('1 / 31 hari');

        // Next month: the same tasks keep their people, so the manager does not hand everything out again.
        app(\App\Services\ProjectTeamService::class)->setAssignees($task('Admin sosmed'), $putri->id, [$putri->id], $manager);
        $january = app(\App\Services\ProjectTemplateService::class)->openPeriod($project, $manager);
        $next = Task::query()->where('project_period_id', $january->id)->whereNull('parent_id')->where('title', 'Admin sosmed')->with('assignees:id')->firstOrFail();
        $this->assertSame($putri->id, (int) $next->pic_user_id);
        $this->assertSame([$putri->id], $next->assignees->pluck('id')->map(fn ($i) => (int) $i)->all());
        $report = Task::query()->where('project_period_id', $january->id)->where('title', 'Laporan konten')->firstOrFail();
        $this->assertSame('2027-01-28 23:59', Dates::local($report->due_at)->format('Y-m-d H:i'), 'tgl 28 again in January');

        // A project created on 10 December (start = today) has no routine days before it existed: 10 to 31 December.
        $this->actingAs($manager)->post(route('projects.store'), ['company_id' => $c->id, 'name' => 'Sosmed Mulai Tengah Bulan', 'project_type' => 'recurring',
            'recurrence_unit' => 'month', 'recurrence_interval' => 1, 'pic_user_id' => $manager->id, 'project_template_id' => $template->id])->assertRedirect();
        $late = Project::query()->where('name', 'Sosmed Mulai Tengah Bulan')->firstOrFail();
        $days = Task::query()->where('project_id', $late->id)->whereNull('parent_id')->where('title', 'Pengawasan insight sosmed')->firstOrFail()->subtasks()->orderBy('sort_order')->pluck('title');
        $this->assertCount(22, $days);
        $this->assertSame('Kamis, 10 Des 10.00-15.00', $days->first());
    }

    public function test_a_project_started_mid_month_can_skip_the_tasks_already_over(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $template = $this->monthlyTemplate($this->user('super_admin', $c));
        Carbon::setTestNow(Carbon::parse('2026-12-10 09:00', Dates::zone()));
        $this->actingAs($manager)->post(route('projects.store'), ['company_id' => $c->id, 'name' => 'Sosmed Mulai Sekarang', 'project_type' => 'recurring',
            'recurrence_unit' => 'month', 'pic_user_id' => $manager->id, 'project_template_id' => $template->id, 'skip_past' => '1'])->assertRedirect();
        $project = Project::query()->where('name', 'Sosmed Mulai Sekarang')->firstOrFail();
        // Kalender konten (1 Dec) and Konten 1 (5 Dec) are over; Konten 2 is due today, so it stays.
        $this->assertSame(['Konten 2', 'Pengawasan insight sosmed', 'Admin sosmed', 'Laporan konten'],
            Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('sort_order')->pluck('title')->all());
        $this->actingAs($manager)->get(route('projects.create'))->assertOk()->assertSee('data-preview', false);
    }

    public function test_preview_of_a_recurring_template_counts_from_the_first_of_the_month(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $template = $this->monthlyTemplate($admin);
        $json = $this->actingAs($admin)->getJson(route('projects.preview', ['template' => $template->id, 'start' => '2026-12-10T09:00', 'unit' => 'month']))->assertOk()->json('tasks');
        $byTitle = collect($json)->keyBy('title');
        $this->assertSame('28 Des 2026', $byTitle['Laporan konten']['due']);
        $this->assertSame('Setiap hari 10.00-15.00', $byTitle['Pengawasan insight sosmed']['routine']);
        $this->assertSame([], $byTitle['Pengawasan insight sosmed']['subtasks']);
    }

    public function test_on_time_kpi_counts_finished_before_the_deadline(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $project = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $tasks = Task::query()->where('project_id', $project->id)->whereNull('parent_id')->orderBy('id')->get();
        $tasks[0]->update(['status' => 'done', 'completed_at' => $tasks[0]->due_at->copy()->subHour()]);
        $tasks[1]->update(['status' => 'done', 'completed_at' => $tasks[1]->due_at->copy()->addDay()]);
        $row = app(ManagementMetricsService::class)->memberWorkload($manager)->firstWhere('id', $manager->id);
        $this->assertSame([2, 1], [(int) $row->done, (int) $row->on_time]);
        $this->actingAs($manager)->get(route('management.teams'))->assertOk()->assertSee('KPI per anggota')->assertSee('50%');
    }
}
