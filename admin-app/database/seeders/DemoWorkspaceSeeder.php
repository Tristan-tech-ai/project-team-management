<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
class DemoWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        if (!app()->environment('local')) {
            throw new RuntimeException('DemoWorkspaceSeeder hanya untuk local.');
        }
        $password = env('DEMO_SEED_PASSWORD');
        if (!$password || strlen($password) < 12) {
            throw new RuntimeException('Set DEMO_SEED_PASSWORD (>=12 karakter).');
        }
        DB::transaction(function () use ($password) {
            $companyId = DB::table('companies')
                ->where('code', 'COMP-A')->value('id');
            $clientId = DB::table('clients')
                ->where('code', 'CLIENT-A')->value('id');
            if (!$companyId || !$clientId) {
                throw new RuntimeException('Jalankan InitialWorkspaceSeeder dahulu.');
            }
            $manager = User::firstOrCreate(
                ['email' => 'demo.manager@example.test'],
                ['name' => 'Demo Manager', 'password' => Hash::make($password)]
            );
            $member = User::firstOrCreate(
                ['email' => 'demo.member@example.test'],
                ['name' => 'Demo Member', 'password' => Hash::make($password)]
            );
            foreach ([[$manager, 'manager'], [$member, 'member']] as [$user, $role]) {
                $roleId = DB::table('roles')->where('slug', $role)->value('id');
                DB::table('user_roles')->updateOrInsert(
                    ['user_id' => $user->id, 'role_id' => $roleId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
                DB::table('company_members')->updateOrInsert(
                    ['company_id' => $companyId, 'user_id' => $user->id],
                    ['is_active' => 1, 'created_at' => now(),
                     'updated_at' => now()]
                );
                DB::table('user_profiles')->updateOrInsert(
                    ['user_id' => $user->id],
                    ['is_active' => 1, 'created_at' => now(),
                     'updated_at' => now()]
                );
            }
            $divisionId = DB::table('divisions')
                ->where('company_id', $companyId)
                ->where('name', 'Development')->value('id');
            $positionId = DB::table('positions')
                ->where('company_id', $companyId)
                ->where('name', 'Web Developer')->value('id');
            foreach ([$manager, $member] as $user) {
                DB::table('division_members')->updateOrInsert(
                    ['division_id' => $divisionId, 'user_id' => $user->id],
                    ['position_id' => $positionId, 'is_active' => 1,
                     'created_at' => now(), 'updated_at' => now()]
                );
            }
            $projectId = DB::table('projects')
                ->where('code', 'DEMO-PROJECT-001')->value('id');
            if (!$projectId) {
                $projectId = DB::table('projects')->insertGetId([
                    'company_id' => $companyId, 'client_id' => $clientId,
                    'pic_user_id' => $manager->id, 'created_by' => $manager->id,
                    'code' => 'DEMO-PROJECT-001',
                    'name' => 'Website Company Profile (Demo)',
                    'project_type' => 'one_time', 'status' => 'active',
                    'start_at' => now(), 'baseline_due_at' => now()->addDays(14),
                    'due_at' => now()->addDays(14),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('project_divisions')->updateOrInsert(
                ['project_id' => $projectId, 'division_id' => $divisionId],
                ['created_at' => now(), 'updated_at' => now()]
            );
            $projectDivisionId = DB::table('project_divisions')
                ->where('project_id', $projectId)
                ->where('division_id', $divisionId)->value('id');
            foreach ([$manager, $member] as $user) {
                DB::table('project_members')->updateOrInsert(
                    ['project_id' => $projectId, 'user_id' => $user->id],
                    ['is_active' => 1, 'joined_at' => now(),
                     'created_at' => now(), 'updated_at' => now()]
                );
                $projectMemberId = DB::table('project_members')
                    ->where('project_id', $projectId)
                    ->where('user_id', $user->id)->value('id');
                DB::table('project_member_divisions')->updateOrInsert(
                    ['project_member_id' => $projectMemberId,
                     'project_division_id' => $projectDivisionId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
            $taskId = DB::table('tasks')
                ->where('project_id', $projectId)
                ->whereNull('parent_id')
                ->where('title', 'Buat Homepage')->value('id');
            if (!$taskId) {
                $taskId = DB::table('tasks')->insertGetId([
                    'project_id' => $projectId, 'pic_user_id' => $manager->id,
                    'created_by' => $manager->id, 'parent_id' => null,
                    'title' => 'Buat Homepage', 'status' => 'backlog',
                    'task_type' => 'development', 'estimated_minutes' => 480,
                    'baseline_due_at' => now()->addDays(7),
                    'due_at' => now()->addDays(7),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ([$manager, $member] as $user) {
                DB::table('task_assignees')->updateOrInsert(
                    ['task_id' => $taskId, 'user_id' => $user->id],
                    ['assigned_by' => $manager->id,
                     'assigned_at' => now(), 'created_at' => now(),
                     'updated_at' => now()]
                );
            }
            foreach (['Buat layout', 'Responsive mobile', 'QA browser'] as $index => $title) {
                if (!DB::table('tasks')->where('parent_id', $taskId)
                    ->where('title', $title)->exists()) {
                    DB::table('tasks')->insert([
                        'project_id' => $projectId, 'parent_id' => $taskId,
                        'pic_user_id' => $member->id,
                        'created_by' => $manager->id,
                        'title' => $title, 'status' => 'backlog',
                        'sort_order' => $index,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        });
    }
}
