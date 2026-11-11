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
 * Data UAT dan video (sintetis, bukan bagian dokumen; hanya lingkungan non-production).
 * Jalankan setelah InitialWorkspaceSeeder dan DemoWorkspaceSeeder: php artisan db:seed --class=UatSkenarioSeeder
 * Membuat tim agensi contoh, dua client, dua project berjalan, dan satu arsip 520 task untuk uji DataTables.
 */
class UatSkenarioSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('UatSkenarioSeeder tidak untuk production.');
        }
        $sandi = env('DEMO_SEED_PASSWORD');
        if (! $sandi || strlen($sandi) < 12) {
            throw new RuntimeException('Set DEMO_SEED_PASSWORD (>=12 karakter).');
        }
        $c = DB::table('companies')->where('code', 'COMP-A')->value('id') ?? throw new RuntimeException('Jalankan InitialWorkspaceSeeder dahulu.');
        $div = DB::table('divisions')->where('company_id', $c)->pluck('id', 'name');
        $jab = DB::table('positions')->where('company_id', $c)->pluck('id', 'name');
        $orang = [
            ['Radit Pratama', 'radit', 'manager', [['Development', 'Project Manager']]],
            ['Ayu Lestari', 'ayu', 'member', [['Creative', 'UI/UX Designer'], ['Development', 'UI/UX Designer']]],
            ['Binda Saputra', 'binda', 'member', [['Creative', 'Graphic Designer']]],
            ['Putri Maharani', 'putri', 'member', [['Digital Marketing', 'Digital Marketer']]],
            ['Dika Ramadhan', 'dika', 'member', [['Development', 'Web Developer']]],
            ['Raka Wijaya', 'raka', 'member', [['Creative', 'Video Editor']]],
        ];
        $sekarang = now();
        $id = [];
        DB::transaction(function () use ($orang, $sandi, $c, $div, $jab, $sekarang, &$id) {
            foreach ($orang as [$nama, $kunci, $role, $divisi]) {
                $u = User::firstOrCreate(['email' => "{$kunci}@example.test"], ['name' => $nama, 'password' => Hash::make($sandi)]);
                $id[$kunci] = $u->id;
                DB::table('user_profiles')->updateOrInsert(['user_id' => $u->id], ['is_active' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
                DB::table('user_roles')->updateOrInsert(['user_id' => $u->id, 'role_id' => DB::table('roles')->where('slug', $role)->value('id')], ['created_at' => $sekarang, 'updated_at' => $sekarang]);
                DB::table('company_members')->updateOrInsert(['company_id' => $c, 'user_id' => $u->id], ['is_active' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
                foreach ($divisi as [$d, $j]) {
                    DB::table('division_members')->updateOrInsert(['division_id' => $div[$d], 'user_id' => $u->id],
                        ['position_id' => $jab[$j] ?? null, 'is_active' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
                }
            }
            DB::table('divisions')->where('id', $div['Development'])->update(['lead_user_id' => $id['radit']]);
            foreach ([['SMA-HB', 'SMA Harapan Bangsa', 'humas@sma-hb.example.test'], ['KOPI-SENJA', 'Kopi Senja', 'halo@kopisenja.example.test']] as [$kode, $nama, $email]) {
                DB::table('clients')->updateOrInsert(['code' => $kode], ['name' => $nama, 'email' => $email, 'is_active' => 1, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
            }
        });

        $radit = User::query()->findOrFail($id['radit']);
        $svc = app(ProjectTemplateService::class);
        $buat = function (string $kode, string $nama, string $template, string $tipe, ?string $client, array $anggota, int $mulaiHari, int $durasi) use ($svc, $c, $div, $radit, $id) {
            if (Project::withTrashed()->where('code', $kode)->exists()) {
                return Project::query()->where('code', $kode)->first();
            }

            return $svc->buatProject([
                'company_id' => $c, 'client_id' => $client ? DB::table('clients')->where('code', $client)->value('id') : null,
                'name' => $nama, 'code' => $kode, 'project_type' => $tipe, 'pic_user_id' => $radit->id,
                'start_at' => now()->subDays($mulaiHari), 'due_at' => now()->subDays($mulaiHari)->addDays($durasi),
                'project_template_id' => ProjectTemplate::query()->where('name', $template)->value('id'),
                'divisions' => array_values($div->all()), 'members' => array_map(fn ($k) => $id[$k], $anggota),
                'recurrence_unit' => 'month', 'recurrence_interval' => 1,
            ], $radit);
        };
        $konten = $buat('KOPI-KONTEN', 'Konten Sosial Media Kopi Senja', 'Produksi Konten', 'recurring', 'KOPI-SENJA', ['binda', 'putri', 'raka'], 20, 40);
        $web = $buat('WEB-MAINT', 'Maintenance Website Company Profile', 'Website Development', 'ongoing', null, ['ayu', 'dika'], 30, 60);

        // Sebagian task sudah berjalan supaya monitoring dan board tidak kosong.
        $alur = app(TaskWorkflowService::class);
        foreach ([[$konten, 'Riset Ide', 'putri'], [$web, 'Analisis Kebutuhan', 'dika']] as [$p, $judul, $kunci]) {
            $t = Task::query()->where('project_id', $p->id)->where('title', $judul)->first();
            if ($t && $t->status === 'backlog') {
                DB::table('task_assignees')->insertOrIgnore(['task_id' => $t->id, 'user_id' => $id[$kunci], 'assigned_by' => $radit->id, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
                $alur->pindah($t, 'in_progress', $radit);
                $s = $alur->kirimHasil($t, User::query()->find($id[$kunci]), 'https://drive.example.test/'.str($judul)->slug(), null, 'Draf pertama');
                $alur->putuskan($s, 'approved', $radit, null);
            }
        }
        $t = Task::query()->where('project_id', $web->id)->where('title', 'Desain UI/UX')->first();
        if ($t && $t->status === 'backlog') {
            DB::table('task_assignees')->insertOrIgnore(['task_id' => $t->id, 'user_id' => $id['ayu'], 'assigned_by' => $radit->id, 'assigned_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $alur->pindah($t, 'in_progress', $radit);
        }

        // Arsip 520 task selesai untuk skenario DataTables server-side (Petunjuk Teknis 11.3 butir 8).
        if (! Project::withTrashed()->where('code', 'ARSIP-2025')->exists()) {
            $arsip = $buat('ARSIP-2025', 'Arsip Operasional 2025', '', 'one_time', null, ['dika'], 400, 300);
            $baris = [];
            for ($i = 1; $i <= 520; $i++) {
                $selesai = now()->subDays(400)->addDays(intdiv($i * 300, 520));
                $baris[] = ['project_id' => $arsip->id, 'pic_user_id' => $id['dika'], 'created_by' => $radit->id, 'completed_by' => $radit->id,
                    'title' => sprintf('Tiket perawatan %03d', $i), 'task_type' => 'development', 'status' => 'done', 'priority' => 'normal',
                    'due_at' => $selesai, 'baseline_due_at' => $selesai, 'completed_at' => $selesai, 'created_at' => $selesai, 'updated_at' => $selesai];
            }
            foreach (array_chunk($baris, 200) as $b) {
                DB::table('tasks')->insert($b);
            }
            $arsip->update(['status' => 'completed', 'completed_at' => now()->subDays(100)]);
        }
    }
}
