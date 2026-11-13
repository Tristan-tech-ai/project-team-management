<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectTemplateService;
use App\Services\TaskWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * UAT and video data (synthetic, not part of the guide; non-production environments only).
 * Run after InitialWorkspaceSeeder and DemoWorkspaceSeeder: php artisan db:seed --class=UatScenarioSeeder
 * Creates a sample agency team, two clients, two running projects, and one 520-task archive for the DataTables test.
 */
class UatScenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('UatSkenarioSeeder tidak untuk production.');
        }
        $password = env('DEMO_SEED_PASSWORD');
        if (! $password || strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_SEED_PASSWORD (>=12 karakter).');
        }
        $c = DB::table('companies')->where('code', 'COMP-A')->value('id') ?? throw new RuntimeException('Jalankan InitialWorkspaceSeeder dahulu.');
        $div = DB::table('divisions')->where('company_id', $c)->pluck('id', 'name');
        $position = DB::table('positions')->where('company_id', $c)->pluck('id', 'name');
        $people = [
            ['Radit Pratama', 'radit', 'manager', [['Development', 'Project Manager']]],
            ['Ayu Lestari', 'ayu', 'member', [['Creative', 'UI/UX Designer'], ['Development', 'UI/UX Designer']]],
            ['Binda Saputra', 'binda', 'member', [['Creative', 'Graphic Designer']]],
            ['Putri Maharani', 'putri', 'member', [['Digital Marketing', 'Digital Marketer']]],
            ['Dika Ramadhan', 'dika', 'member', [['Development', 'Web Developer']]],
            ['Raka Wijaya', 'raka', 'member', [['Creative', 'Video Editor']]],
        ];
        $now = now();
        $id = [];
        DB::transaction(function () use ($people, $password, $c, $div, $position, $now, &$id) {
            foreach ($people as [$name, $key, $role, $divisions]) {
                $u = User::firstOrCreate(['email' => "{$key}@example.test"], ['name' => $name, 'password' => Hash::make($password)]);
                $id[$key] = $u->id;
                DB::table('user_profiles')->updateOrInsert(['user_id' => $u->id], ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('user_roles')->updateOrInsert(['user_id' => $u->id, 'role_id' => DB::table('roles')->where('slug', $role)->value('id')], ['created_at' => $now, 'updated_at' => $now]);
                DB::table('company_members')->updateOrInsert(['company_id' => $c, 'user_id' => $u->id], ['is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
                foreach ($divisions as [$d, $j]) {
                    DB::table('division_members')->updateOrInsert(['division_id' => $div[$d], 'user_id' => $u->id],
                        ['position_id' => $position[$j] ?? null, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
            DB::table('divisions')->where('id', $div['Development'])->update(['lead_user_id' => $id['radit']]);
            foreach ([['SMA-HB', 'SMA Harapan Bangsa', 'humas@sma-hb.example.test'], ['KOPI-SENJA', 'Kopi Senja', 'halo@kopisenja.example.test']] as [$code, $name, $email]) {
                DB::table('clients')->updateOrInsert(['code' => $code], ['name' => $name, 'email' => $email, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        });

        $radit = User::query()->findOrFail($id['radit']);
        $svc = app(ProjectTemplateService::class);
        $create = function (string $code, string $name, string $template, string $type, ?string $client, array $members, int $dayStart, int $duration) use ($svc, $c, $div, $radit, $id) {
            if (Project::withTrashed()->where('code', $code)->exists()) {
                return Project::query()->where('code', $code)->first();
            }

            return $svc->createProject([
                'company_id' => $c, 'client_id' => $client ? DB::table('clients')->where('code', $client)->value('id') : null,
                'name' => $name, 'code' => $code, 'project_type' => $type, 'pic_user_id' => $radit->id,
                'start_at' => now()->subDays($dayStart), 'due_at' => now()->subDays($dayStart)->addDays($duration),
                'project_template_id' => ProjectTemplate::query()->where('name', $template)->value('id'),
                'divisions' => array_values($div->all()), 'members' => array_map(fn ($k) => $id[$k], $members),
                'recurrence_unit' => 'month', 'recurrence_interval' => 1,
            ], $radit);
        };
        $pageContent = $create('KOPI-KONTEN', 'Konten Sosial Media Kopi Senja', 'Produksi Konten', 'recurring', 'KOPI-SENJA', ['binda', 'putri', 'raka'], 20, 40);
        $web = $create('WEB-MAINT', 'Maintenance Website Company Profile', 'Website Development', 'ongoing', null, ['ayu', 'dika'], 30, 60);

        // Some tasks are already in progress so monitoring and the board are not empty.
        $workflow = app(TaskWorkflowService::class);
        foreach ([[$pageContent, 'Riset Ide', 'putri'], [$web, 'Analisis Kebutuhan', 'dika']] as [$p, $title, $key]) {
            $t = Task::query()->where('project_id', $p->id)->where('title', $title)->first();
            if ($t && $t->status === 'backlog') {
                DB::table('task_assignees')->insertOrIgnore(['task_id' => $t->id, 'user_id' => $id[$key], 'assigned_by' => $radit->id, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $workflow->move($t, 'in_progress', $radit);
                $s = $workflow->submitResult($t, User::query()->find($id[$key]), 'https://drive.example.test/'.str($title)->slug(), null, 'Draf pertama');
                $workflow->decide($s, 'approved', $radit, null);
            }
        }
        $t = Task::query()->where('project_id', $web->id)->where('title', 'Desain UI/UX')->first();
        if ($t && $t->status === 'backlog') {
            DB::table('task_assignees')->insertOrIgnore(['task_id' => $t->id, 'user_id' => $id['ayu'], 'assigned_by' => $radit->id, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $workflow->move($t, 'in_progress', $radit);
        }

        // Archive of 520 done tasks for the server-side DataTables scenario (Technical Guide 11.3 item 8).
        if (! Project::withTrashed()->where('code', 'ARSIP-2025')->exists()) {
            $archive = $create('ARSIP-2025', 'Arsip Operasional 2025', '', 'one_time', null, ['dika'], 400, 300);
            $rows = [];
            for ($i = 1; $i <= 520; $i++) {
                $done = now()->subDays(400)->addDays(intdiv($i * 300, 520));
                $rows[] = ['project_id' => $archive->id, 'pic_user_id' => $id['dika'], 'created_by' => $radit->id, 'completed_by' => $radit->id,
                    'title' => sprintf('Tiket perawatan %03d', $i), 'task_type' => 'development', 'status' => 'done', 'priority' => 'normal',
                    'due_at' => $done, 'baseline_due_at' => $done, 'completed_at' => $done, 'created_at' => $done, 'updated_at' => $done];
            }
            foreach (array_chunk($rows, 200) as $b) {
                DB::table('tasks')->insert($b);
            }
            $archive->update(['status' => 'completed', 'completed_at' => now()->subDays(100)]);
        }
    }
}
