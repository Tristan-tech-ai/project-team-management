<?php

namespace Tests\Feature;

use App\Http\Middleware\CatatAksi;
use App\Models\AuditLog;
use App\Support\Pengaturan;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\Domain\Pembantu;
use Tests\TestCase;

/** Petunjuk Teknis 4.1: semua aksi Super Admin dicatat; log hanya bisa dibuka Super Admin; sandi tidak pernah disimpan. */
class AuditLogTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    protected function tearDown(): void
    {
        Waktu::lupakan();
        Pengaturan::lupakan();
        parent::tearDown();
    }

    public function test_aksi_super_admin_dicatat_tanpa_sandi(): void
    {
        $admin = $this->pengguna('super_admin', $this->company());
        $awal = AuditLog::query()->max('id') ?? 0;

        $this->post('/masuk', ['email' => $admin->email, 'password' => 'sandi-uji-123456'])->assertRedirect();
        $this->put('/settings/general', ['timezone' => 'Asia/Jakarta', 'table_page_length' => '25'])->assertRedirect();
        $this->post('/keluar')->assertRedirect();

        $log = AuditLog::query()->where('id', '>', $awal)->where('user_id', $admin->id)->orderBy('id')->get();
        $this->assertSame(['POST', 'PUT', 'POST'], $log->pluck('method')->all());
        $this->assertSame(['/masuk', '/settings/general', '/keluar'], $log->pluck('path')->all());
        $this->assertSame('super_admin', $log[0]->role);
        $this->assertSame('admin', $log[0]->app);
        $this->assertSame('[disembunyikan]', $log[0]->input['password']);
        $this->assertSame($admin->email, $log[0]->input['email']);
        $this->assertSame('settings.general.update', $log[1]->route);
        $this->assertSame('Asia/Jakarta', $log[1]->input['timezone']);
        $this->assertSame(302, $log[1]->status);
        $this->assertArrayNotHasKey('_method', $log[1]->input);
        $this->assertStringNotContainsString('sandi-uji-123456', json_encode($log->pluck('input')->all()));
    }

    public function test_aksi_ditolak_tetap_dicatat_dan_get_tidak(): void
    {
        $c = $this->company();
        $manager = $this->pengguna('manager', $c);
        $awal = AuditLog::query()->max('id') ?? 0;

        $this->actingAs($manager)->get('/dashboard')->assertOk();
        $this->actingAs($manager)->put('/settings/general', ['timezone' => 'UTC', 'table_page_length' => '10'])->assertForbidden();

        $log = AuditLog::query()->where('id', '>', $awal)->where('user_id', $manager->id)->get();
        $this->assertCount(1, $log);
        $this->assertSame([403, 'manager'], [$log[0]->status, $log[0]->role]);
    }

    public function test_tamu_tidak_dicatat(): void
    {
        $awal = AuditLog::query()->max('id') ?? 0;
        $this->post('/masuk', ['email' => 'tidak-ada@uji.test', 'password' => 'salah-sandi-123'])->assertSessionHasErrors('email');
        $this->assertSame(0, AuditLog::query()->where('id', '>', $awal)->count());
    }

    public function test_pembersih_isian(): void
    {
        $hasil = CatatAksi::bersihkan([
            'password_confirmation' => 'x', 'current_password' => 'y', 'api_token' => 'z', 'sandi_baru' => 'w',
            'judul' => str_repeat('a', 400), 'berkas' => UploadedFile::fake()->create('hasil.pdf', 10),
            'anggota' => [3, 4], 'rincian' => ['secret_key' => 'k', 'nama' => 'Budi'],
        ]);
        foreach (['password_confirmation', 'current_password', 'api_token', 'sandi_baru'] as $k) {
            $this->assertSame('[disembunyikan]', $hasil[$k]);
        }
        $this->assertSame(300, mb_strlen($hasil['judul']));
        $this->assertSame('berkas: hasil.pdf', $hasil['berkas']);
        $this->assertSame([3, 4], $hasil['anggota']);
        $this->assertSame(['secret_key' => '[disembunyikan]', 'nama' => 'Budi'], $hasil['rincian']);
    }

    public function test_halaman_log_hanya_super_admin(): void
    {
        $c = $this->company();
        $admin = $this->pengguna('super_admin', $c);
        $this->actingAs($admin)->put('/settings/general', ['timezone' => 'Asia/Makassar', 'table_page_length' => '25']);

        $html = $this->actingAs($admin)->get('/settings/audit?cari='.urlencode($admin->email))->assertOk()->getContent();
        $this->assertStringContainsString($admin->name, $html);
        $this->assertStringContainsString('settings.general.update', $html);
        $this->actingAs($admin)->get('/settings/general')->assertOk()->assertSee(route('settings.audit'), false);
        $this->actingAs($admin)->get('/settings/audit?cari=tidak-ada-yang-cocok-xyz')->assertOk()->assertSee('Tidak ada aksi yang cocok');

        foreach (['manager', 'member'] as $r) {
            $this->actingAs($this->pengguna($r, $c))->get('/settings/audit')->assertForbidden();
        }
        $this->post('/keluar');
        $this->get('/settings/audit')->assertRedirect('/masuk');
    }
}
