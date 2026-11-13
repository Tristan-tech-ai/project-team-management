<?php

namespace Tests\Domain;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\ProjectTeamService;
use App\Support\Files;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Supervisor revision: files uploaded in the Team App must open in the Admin App. Both apps use the `shared` disk
 * that points to admin-app storage. This test file is identical in both apps (tests/Domain is copied by sync-domain.php).
 */
class SharedStorageTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ptm-test-'.Str::lower(Str::random(8));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    /** Points the shared disk to a temporary folder so the test never writes to real storage. */
    private function useTemporaryDisk(): void
    {
        config(['filesystems.disks.shared.root' => $this->root]);
        Storage::forgetDisk(Files::DISK);
    }

    public function test_both_apps_point_to_admin_app_storage(): void
    {
        if (getenv('PTM_STORAGE_ROOT')) {
            $this->markTestSkipped('PTM_STORAGE_ROOT diatur, lokasi bersama ditentukan env.');
        }
        $expected = realpath(dirname(base_path()).'/admin-app/storage/app/private');
        $this->assertNotFalse($expected, 'The admin-app/storage/app/private folder must exist next to this app.');
        $this->assertSame($expected, realpath(config('filesystems.disks.shared.root')));
    }

    public function test_submitted_files_use_shared_disk_and_download_respects_access(): void
    {
        $this->useTemporaryDisk();
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $a = $this->user('member', $c);
        $outsider = $this->user('member', $this->company('Company Lain'));
        $p = $this->project($manager, $manager, $c, [$a], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        app(ProjectTeamService::class)->setAssignees($t, $a->id, [$a->id], $manager);

        $this->actingAs($a)->patchJson("/tasks/{$t->id}/status", ['status' => 'in_progress'])->assertOk();
        $file = UploadedFile::fake()->createWithContent('hasil-desain.pdf', '%PDF-1.4 isi uji lampiran bersama');
        $this->actingAs($a)->post("/tasks/{$t->id}/submissions", ['file' => $file, 'submission_note' => 'Siap direview'])->assertSessionHasNoErrors();

        $attachment = TaskAttachment::query()->where('task_id', $t->id)->whereNotNull('storage_path')->firstOrFail();
        $this->assertSame(Files::DISK, $attachment->storage_disk);
        $this->assertFileExists($this->root.'/'.$attachment->storage_path);

        $r = $this->actingAs($manager)->get("/attachments/{$attachment->id}")->assertOk();
        $this->assertSame('%PDF-1.4 isi uji lampiran bersama', $r->streamedContent());
        $this->assertStringContainsString('hasil-desain.pdf', (string) $r->headers->get('content-disposition'));
        $this->actingAs($outsider)->get("/attachments/{$attachment->id}")->assertForbidden();
    }

    public function test_legacy_attachments_on_local_disk_remain_readable(): void
    {
        $this->useTemporaryDisk();
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $p = $this->project($manager, $manager, $c, [], [], 'one_time', $this->template());
        $t = Task::query()->where('project_id', $p->id)->where('title', 'Desain')->firstOrFail();
        $path = 'attachments/legacy-test-'.Str::random(6).'.txt';
        Storage::disk('local')->put($path, 'berkas lama');
        try {
            $l = TaskAttachment::create(['task_id' => $t->id, 'uploaded_by' => $manager->id, 'original_name' => 'old.txt', 'storage_disk' => 'local', 'storage_path' => $path]);
            $this->assertSame('berkas lama', $this->actingAs($manager)->get("/attachments/{$l->id}")->assertOk()->streamedContent());
        } finally {
            Storage::disk('local')->delete($path);
        }
    }

    public function test_profile_photo_on_shared_disk_is_visible_to_owner_and_super_admin_only(): void
    {
        $this->useTemporaryDisk();
        $c = $this->company();
        $a = $this->user('member', $c);
        $b = $this->user('member', $c);
        $admin = $this->user('super_admin', $c);
        // PNG 1x1 piksel.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        $this->actingAs($a)->put('/account', ['name' => $a->name, 'photo' => UploadedFile::fake()->createWithContent('photo.png', $png)])->assertSessionHasNoErrors();

        $path = DB::table('user_profiles')->where('user_id', $a->id)->value('avatar_path');
        $this->assertNotNull($path);
        $this->assertFileExists($this->root.'/'.$path);
        $this->assertSame($png, $this->actingAs($a)->get("/account/photo/{$a->id}")->assertOk()->streamedContent());
        $this->actingAs($admin)->get("/account/photo/{$a->id}")->assertOk();
        $this->actingAs($b)->get("/account/photo/{$a->id}")->assertForbidden();
        $this->actingAs($a)->get('/account')->assertOk()->assertSee("/account/photo/{$a->id}", false);
    }
}
