<?php

namespace Tests\Feature;

use App\Models\Position;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Domain\Pembantu;
use Tests\TestCase;

/** LV-01: Anggota Tim (user + role + company + divisi + jabatan) dan halaman galat yang ramah (LV-05). */
class AnggotaGalatTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_tambah_anggota_dengan_role_company_divisi_jabatan_lalu_bisa_masuk(): void
    {
        $c = $this->company();
        $dev = $this->divisi($c, 'Development');
        $kreatif = $this->divisi($c, 'Creative');
        $jabatan = Position::create(['company_id' => $c->id, 'name' => 'Web Developer Uji', 'is_active' => 1]);
        $admin = $this->pengguna('super_admin', $c);
        $this->actingAs($admin)->post('/master/members', [
            'name' => 'Sinta Pratama', 'email' => 'sinta.uji@example.test', 'password' => 'SandiKuat12345', 'role' => 'member', 'is_active' => 1,
            'companies' => [$c->id], 'divisi' => [$dev->id => ['ikut' => 1, 'position_id' => $jabatan->id], $kreatif->id => ['ikut' => 1]],
        ])->assertRedirect('/master/members');
        $u = User::query()->where('email', 'sinta.uji@example.test')->firstOrFail();
        $this->assertSame(['member'], $u->roleSlugs());
        $this->assertSame(2, DB::table('division_members')->where('user_id', $u->id)->where('is_active', 1)->count());
        $this->assertSame($jabatan->id, DB::table('division_members')->where('user_id', $u->id)->where('division_id', $dev->id)->value('position_id'));
        $this->post('/keluar');
        $this->post('/masuk', ['email' => 'sinta.uji@example.test', 'password' => 'SandiKuat12345'])->assertRedirect(route('beranda'));
    }

    public function test_divisi_dari_company_lain_ditolak(): void
    {
        $c = $this->company();
        $lain = $this->divisi($this->company('Lain'), 'Asing');
        $admin = $this->pengguna('super_admin', $c);
        $this->actingAs($admin)->post('/master/members', ['name' => 'X', 'email' => 'x.uji@example.test', 'password' => 'SandiKuat12345', 'role' => 'member',
            'companies' => [$c->id], 'divisi' => [$lain->id => ['ikut' => 1]]])->assertSessionHasErrors('divisi');
        $this->assertFalse(User::query()->where('email', 'x.uji@example.test')->exists());
    }

    public function test_halaman_galat_ramah(): void
    {
        $admin = $this->pengguna('super_admin', $this->company());
        $this->actingAs($admin)->get('/projects/99999999')->assertNotFound()->assertSee('Halaman tidak ditemukan');
        $member = $this->pengguna('member', $this->company());
        $this->actingAs($member)->get('/settings/roles')->assertForbidden()->assertSee('bukan untuk role-mu');
    }
}
