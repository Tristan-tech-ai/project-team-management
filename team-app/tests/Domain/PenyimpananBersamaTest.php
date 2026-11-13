<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\ProjectTeamService;
use App\Support\Berkas;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Revisi pembina: berkas yang diunggah di Team App harus bisa dibuka di Admin App. Kedua app memakai disk `bersama`
 * yang menunjuk storage admin-app. Berkas tes ini sama di kedua app (tests/Domain disalin oleh sinkron-domain.php).
 */
class PenyimpananBersamaTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    private string $akar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->akar = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ptm-uji-'.Str::lower(Str::random(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->akar);
        parent::tearDown();
    }

    /** Arahkan disk bersama ke folder sementara supaya tes tidak menulis ke storage sungguhan. */
    private function diskSementara(): void
    {
        config(['filesystems.disks.bersama.root' => $this->akar]);
        Storage::forgetDisk(Berkas::DISK);
    }

    public function test_kedua_app_menunjuk_folder_storage_admin_app(): void
    {
        if (getenv('PTM_STORAGE_ROOT')) {
            $this->markTestSkipped('PTM_STORAGE_ROOT diatur, lokasi bersama ditentukan env.');
        }
        $harap = realpath(dirname(base_path()).'/admin-app/storage/app/private');
        $this->assertNotFalse($harap, 'Folder admin-app/storage/app/private harus ada di sebelah app ini.');
        $this->assertSame($harap, realpath(config('filesystems.disks.bersama.root')));
    }

    public function test_hasil_berkas_disimpan_di_disk_bersama_dan_diunduh_sesuai_akses(): void
    {
        $this->diskSementara();
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $a = $this->pengguna('member', $c);
        $luar = $this->pengguna('member', $this->company('Company Lain'));
        $p = $this->project($manager, $manager, $c, [$a], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->aturAssignee($t, $a->id, [$a->id], $manager);

        $this->actingAs($a)->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk();
        $berkas = UploadedFile::fake()->createWithContent('hasil-desain.pdf', '%PDF-1.4 isi uji lampiran bersama');
        $this->actingAs($a)->post("/tasks/{$t->id}/submissions", ['berkas' => $berkas, 'submission_note' => 'Siap direview'])->assertSessionHasNoErrors();

        $lampiran = TaskAttachment::query()->where('task_id', $t->id)->whereNotNull('storage_path')->firstOrFail();
        $this->assertSame(Berkas::DISK, $lampiran->storage_disk);
        $this->assertFileExists($this->akar.'/'.$lampiran->storage_path);

        $r = $this->actingAs($manager)->get("/lampiran/{$lampiran->id}")->assertOk();
        $this->assertSame('%PDF-1.4 isi uji lampiran bersama', $r->streamedContent());
        $this->assertStringContainsString('hasil-desain.pdf', (string) $r->headers->get('content-disposition'));
        $this->actingAs($luar)->get("/lampiran/{$lampiran->id}")->assertForbidden();
    }

    public function test_lampiran_lama_di_disk_local_tetap_terbaca(): void
    {
        $this->diskSementara();
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        $path = 'lampiran/uji-lama-'.Str::random(6).'.txt';
        Storage::disk('local')->put($path, 'berkas lama');
        try {
            $l = TaskAttachment::create(['task_id' => $t->id, 'uploaded_by' => $manager->id, 'original_name' => 'lama.txt', 'storage_disk' => 'local', 'storage_path' => $path]);
            $this->assertSame('berkas lama', $this->actingAs($manager)->get("/lampiran/{$l->id}")->assertOk()->streamedContent());
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function test_foto_profil_di_disk_bersama_hanya_untuk_pemilik_dan_super_admin(): void
    {
        $this->diskSementara();
        $c = $this->company();
        $a = $this->pengguna('member', $c);
        $b = $this->pengguna('member', $c);
        $admin = $this->pengguna('super_admin', $c);
        // PNG 1x1 piksel.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $this->actingAs($a)->put('/akun', ['name' => $a->name, 'foto' => UploadedFile::fake()->createWithContent('foto.png', $png)])->assertSessionHasNoErrors();

        $path = DB::table('user_profiles')->where('user_id', $a->id)->value('avatar_path');
        $this->assertNotNull($path);
        $this->assertFileExists($this->akar.'/'.$path);
        $this->assertSame($png, $this->actingAs($a)->get("/akun/foto/{$a->id}")->assertOk()->streamedContent());
        $this->actingAs($admin)->get("/akun/foto/{$a->id}")->assertOk();
        $this->actingAs($b)->get("/akun/foto/{$a->id}")->assertForbidden();
        $this->actingAs($a)->get('/akun')->assertOk()->assertSee("/akun/foto/{$a->id}", false);
    }
}
