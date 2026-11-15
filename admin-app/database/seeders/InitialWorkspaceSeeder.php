<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
class InitialWorkspaceSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('INITIAL_ADMIN_EMAIL');
        $password = env('INITIAL_ADMIN_PASSWORD');
        if (!$email || !$password || strlen($password) < 12) {
            throw new RuntimeException(
                'Set INITIAL_ADMIN_EMAIL dan INITIAL_ADMIN_PASSWORD (>=12 karakter).'
            );
        }
        if (!app()->environment('local') && $password === \App\Support\DemoAccounts::DEFAULT_PASSWORD) {
            throw new RuntimeException('Sandi demo bawaan hanya untuk lokal. Isi sandi lain yang kuat.');
        }
        DB::transaction(function () use ($email, $password) {
            // Roles, permissions, and menus are ALREADY created by the initial SQL.
            $roleId = DB::table('roles')
                ->where('slug', 'super_admin')->value('id');
            if (!$roleId) {
                throw new RuntimeException('Import SQL modul terlebih dahulu.');
            }
            $admin = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Super Admin', 'password' => Hash::make($password)]
            );
            DB::table('user_roles')->updateOrInsert(
                ['user_id' => $admin->id, 'role_id' => $roleId],
                ['updated_at' => now(), 'created_at' => now()]
            );
            DB::table('user_profiles')->updateOrInsert(
                ['user_id' => $admin->id],
                ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
            DB::table('companies')->updateOrInsert(
                ['code' => 'COMP-A'],
                ['name' => 'Company A', 'is_active' => 1,
                 'updated_at' => now(), 'created_at' => now()]
            );
            $companyId = DB::table('companies')
                ->where('code', 'COMP-A')->value('id');
            DB::table('company_members')->updateOrInsert(
                ['company_id' => $companyId, 'user_id' => $admin->id],
                ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
            );
            DB::table('clients')->updateOrInsert(
                ['code' => 'CLIENT-A'],
                ['name' => 'Client A', 'is_active' => 1,
                 'updated_at' => now(), 'created_at' => now()]
            );
            foreach (['Development', 'Creative', 'Digital Marketing'] as $name) {
                DB::table('divisions')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $name],
                    ['is_active' => 1, 'updated_at' => now(),
                     'created_at' => now()]
                );
            }
            foreach ([
                'Project Manager', 'Web Developer', 'UI/UX Designer',
                'Graphic Designer', 'Video Editor', 'Digital Marketer',
            ] as $name) {
                DB::table('positions')->updateOrInsert(
                    ['company_id' => $companyId, 'name' => $name],
                    ['is_active' => 1, 'updated_at' => now(),
                     'created_at' => now()]
                );
            }
            // Main template; idempotent by name.
            foreach ([
                'Website Development' => [
                    'Analisis Kebutuhan', 'Desain UI/UX',
                    'Frontend', 'Backend', 'Testing & Deployment',
                ],
                'Produksi Konten' => [
                    'Riset Ide', 'Content Brief',
                    'Desain / Editing', 'Review', 'Publikasi',
                ],
                'Digital Marketing Campaign' => [
                    'Riset Audiens', 'Strategi Campaign',
                    'Materi Iklan', 'Monitoring', 'Laporan',
                ],
            ] as $templateName => $taskNames) {
                $templateId = DB::table('project_templates')
                    ->where('name', $templateName)->value('id');
                if (!$templateId) {
                    $type = match ($templateName) {
                        'Produksi Konten' => 'recurring',
                        'Digital Marketing Campaign' => 'ongoing',
                        default => 'one_time',
                    };
                    $templateId = DB::table('project_templates')->insertGetId([
                        'name' => $templateName, 'project_type' => $type,
                        'created_by' => $admin->id, 'is_active' => 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                foreach ($taskNames as $index => $title) {
                    $exists = DB::table('project_template_tasks')
                        ->where('project_template_id', $templateId)
                        ->whereNull('parent_id')
                        ->where('title', $title)->exists();
                    if (!$exists) {
                        DB::table('project_template_tasks')->insert([
                            'project_template_id' => $templateId,
                            'parent_id' => null, 'title' => $title,
                            'task_type' => 'general', 'sort_order' => $index,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
            // Standalone task templates, each with a subtask checklist.
            foreach ([
                'Desain Feed Instagram' => [
                    'Brief desain', 'Desain awal', 'Review', 'Export final',
                ],
                'Video Pendek' => [
                    'Riset / script', 'Pengambilan bahan',
                    'Editing', 'Caption / thumbnail',
                ],
                'Implementasi Halaman Web' => [
                    'Buat layout', 'Responsive', 'Integrasi data', 'Testing',
                ],
            ] as $taskName => $subtaskNames) {
                $taskTemplateId = DB::table('task_templates')
                    ->where('name', $taskName)->value('id');
                if (!$taskTemplateId) {
                    $taskTemplateId = DB::table('task_templates')->insertGetId([
                        'name' => $taskName, 'task_type' => 'general',
                        'created_by' => $admin->id, 'is_active' => 1,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                foreach ($subtaskNames as $index => $title) {
                    if (!DB::table('task_template_subtasks')
                        ->where('task_template_id', $taskTemplateId)
                        ->where('title', $title)->exists()) {
                        DB::table('task_template_subtasks')->insert([
                            'task_template_id' => $taskTemplateId,
                            'title' => $title, 'sort_order' => $index,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }
}
