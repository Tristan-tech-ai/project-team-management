<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectTeamService;
use App\Services\ProjectTemplateService;
use App\Services\ScheduleChangeService;
use App\Services\TaskWorkflowService;
use App\Support\DemoAccounts;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * I18 lively sample data for the demo accounts (synthetic, local only; run by php artisan ptm:setup after UatScenarioSeeder).
 * The demo Manager leads a running campaign where the demo Member has one task in every situation a reviewer should see:
 * to start, in progress with a checklist, waiting for review, returned for revision, late, and due tomorrow; plus a pending
 * deadline request, a client meeting, a visit, and a blocker. Every change goes through the same services as the app.
 */
class ShowcaseSeeder extends Seeder
{
    public const CODE = 'PPDB-SMAHB';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('ShowcaseSeeder tidak untuk production.');
        }
        if (Project::withTrashed()->where('code', self::CODE)->exists()) {
            return;
        }
        $manager = User::query()->where('email', DemoAccounts::MANAGER_EMAIL)->first();
        $member = User::query()->where('email', DemoAccounts::MEMBER_EMAIL)->first();
        $company = DB::table('companies')->where('code', 'COMP-A')->value('id');
        if (! $manager || ! $member || ! $company) {
            throw new RuntimeException('Jalankan InitialWorkspaceSeeder dan DemoWorkspaceSeeder dahulu.');
        }
        $people = User::query()->whereIn('email', ['ayu@example.test', 'binda@example.test', 'putri@example.test'])->get();
        $client = DB::table('clients')->where('code', 'SMA-HB')->value('id') ?? DB::table('clients')->where('code', 'CLIENT-A')->value('id');

        $project = app(ProjectTemplateService::class)->createProject([
            'company_id' => $company, 'client_id' => $client, 'name' => 'Campaign PPDB SMA Harapan Bangsa', 'code' => self::CODE,
            'project_type' => 'ongoing', 'pic_user_id' => $manager->id, 'start_at' => now()->subDays(12), 'due_at' => now()->addDays(30),
            'project_template_id' => ProjectTemplate::query()->where('name', 'Digital Marketing Campaign')->value('id'),
            'divisions' => DB::table('divisions')->where('company_id', $company)->pluck('id')->all(),
            'members' => array_merge([$member->id], $people->pluck('id')->all()), 'recurrence_unit' => 'month', 'recurrence_interval' => 1,
        ], $manager);

        $team = app(ProjectTeamService::class);
        $flow = app(TaskWorkflowService::class);
        $task = fn (string $title) => Task::query()->where('project_id', $project->id)->whereNull('parent_id')->where('title', $title)->firstOrFail();
        $checklist = function (Task $t, array $items, int $done) use ($manager) {
            foreach ($items as $i => $title) {
                Task::create(['project_id' => $t->project_id, 'project_period_id' => $t->project_period_id, 'parent_id' => $t->id, 'pic_user_id' => $t->pic_user_id,
                    'created_by' => $manager->id, 'title' => $title, 'task_type' => 'general', 'status' => $i < $done ? 'done' : 'backlog', 'priority' => 'normal',
                    'sort_order' => $i, 'completed_at' => $i < $done ? now()->subDays(2) : null]);
            }
        };

        // Riset Audiens: approved and done.
        $research = $task('Riset Audiens');
        $team->setAssignees($research, $member->id, [$member->id], $manager);
        $research->update(['due_at' => now()->subDays(6), 'baseline_due_at' => now()->subDays(6)]);
        $checklist($research->refresh(), ['Survei 30 siswa kelas 9', 'Rangkum kanal favorit'], 2);
        $flow->move($research, 'in_progress', $member);
        $flow->decide($flow->submitResult($research->refresh(), $member, 'https://drive.example.test/riset-audiens-ppdb', null, 'Hasil survei dan persona'), 'approved', $manager, null);

        // Strategi Campaign: waiting for the manager's review.
        $strategy = $task('Strategi Campaign');
        $team->setAssignees($strategy, $member->id, [$member->id, $people->first()?->id ?? $member->id], $manager);
        $strategy->update(['due_at' => now()->addDays(1)->setTime(17, 0), 'baseline_due_at' => now()->addDays(1)->setTime(17, 0)]);
        $checklist($strategy->refresh(), ['Tentukan pesan utama', 'Jadwal unggah 4 minggu', 'Anggaran iklan'], 3);
        $flow->move($strategy, 'in_progress', $member);
        $flow->submitResult($strategy->refresh(), $member, 'https://drive.example.test/strategi-ppdb', null, 'Strategi 4 minggu, siap ditinjau');

        // Materi Iklan: returned for revision with a note.
        $ads = $task('Materi Iklan');
        $team->setAssignees($ads, $member->id, [$member->id], $manager);
        $ads->update(['due_at' => now()->addDays(3)->setTime(17, 0), 'baseline_due_at' => now()->addDays(3)->setTime(17, 0)]);
        $checklist($ads->refresh(), ['Desain feed 1:1', 'Video 15 detik', 'Caption dan tagar'], 1);
        $flow->move($ads, 'in_progress', $member);
        $flow->decide($flow->submitResult($ads->refresh(), $member, 'https://drive.example.test/materi-iklan-v1', null, 'Versi pertama'),
            'revision', $manager, 'Logo sekolah kurang besar dan tanggal pendaftaran belum ada');

        // Monitoring: late, with a deadline request waiting for the manager.
        $monitoring = $task('Monitoring');
        $team->setAssignees($monitoring, $member->id, [$member->id], $manager);
        $monitoring->update(['due_at' => now()->subDays(2)->setTime(17, 0), 'baseline_due_at' => now()->subDays(2)->setTime(17, 0)]);
        $flow->move($monitoring->refresh(), 'in_progress', $member);
        app(ScheduleChangeService::class)->propose($project, $monitoring, null, now()->addDays(5)->setTime(17, 0), 'Akses akun iklan sekolah baru diberikan kemarin', $member);

        // Laporan: still to start.
        $report = $task('Laporan');
        $team->setAssignees($report, $member->id, [$member->id], $manager);

        // Time logged, a client meeting, a visit, and a blocker.
        foreach ([[$research, 120, 6], [$strategy, 180, 2], [$ads, 90, 1]] as [$t, $minutes, $daysAgo]) {
            DB::table('task_time_entries')->insert(['task_id' => $t->id, 'user_id' => $member->id, 'work_date' => now()->subDays($daysAgo)->toDateString(),
                'minutes_spent' => $minutes, 'description' => null, 'created_at' => now(), 'updated_at' => now()]);
        }
        $activities = [
            ['meeting', 'Rapat kick-off dengan humas sekolah', 'done', 10, 90, 'none', null, [$manager->id, $member->id]],
            ['visit', 'Kunjungan foto kegiatan ekstrakurikuler', 'done', 4, 180, 'none', null, [$member->id]],
            ['blocker', 'Akun iklan sekolah belum diberi akses', 'done', 3, null, 'delay', 'Monitoring iklan tertunda sampai akses diberikan', [$member->id]],
            ['meeting', 'Presentasi materi iklan ke kepala sekolah', 'planned', -2, 60, 'none', null, [$manager->id, $member->id]],
        ];
        foreach ($activities as [$type, $title, $status, $daysAgo, $minutes, $impact, $notes, $participants]) {
            $when = now()->subDays($daysAgo)->setTime(10, 0);
            $id = DB::table('project_activities')->insertGetId(['project_id' => $project->id, 'task_id' => null, 'created_by' => $member->id, 'activity_type' => $type,
                'title' => $title, 'status' => $status, 'scheduled_at' => $when, 'occurred_at' => $status === 'done' ? $when : null, 'duration_minutes' => $minutes,
                'impact_type' => $impact, 'impact_notes' => $notes, 'created_at' => $when, 'updated_at' => $when]);
            foreach ($participants as $uid) {
                DB::table('project_activity_participants')->insert(['project_activity_id' => $id, 'user_id' => $uid, 'created_at' => $when, 'updated_at' => $when]);
            }
        }
    }
}
