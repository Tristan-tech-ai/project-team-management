<?php

namespace Tests\Feature;

use App\Support\Pengaturan;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Domain\Pembantu;
use Tests\TestCase;

/** Pengaturan Sistem: tersimpan di kolom JSON system_settings dan benar-benar dipakai (zona waktu tampilan, baris per halaman tabel). */
class PengaturanSistemTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    protected function tearDown(): void
    {
        Waktu::lupakan();
        Pengaturan::lupakan();
        parent::tearDown();
    }

    public function test_simpan_pengaturan_dan_dipakai(): void
    {
        $admin = $this->pengguna('super_admin', $this->company());
        $this->actingAs($admin)->put('/settings/general', ['timezone' => 'Asia/Jakarta', 'table_page_length' => '50'])
            ->assertRedirect()->assertSessionHas('ok');

        $this->assertSame('"Asia/Jakarta"', DB::table('system_settings')->where('setting_key', 'timezone')->value('setting_value'));
        $this->assertSame('Asia/Jakarta', Waktu::zona());
        $this->assertSame(50, Pengaturan::panjangTabel());
        $this->actingAs($admin)->get('/projects')->assertOk()->assertSee('data-panjang-tabel="50"', false);
        $this->assertMatchesRegularExpression('#value="Asia/Jakarta"\s+selected#', $this->actingAs($admin)->get('/settings/general')->assertOk()->getContent());

        $this->actingAs($admin)->put('/settings/general', ['timezone' => 'Asia/Makassar', 'table_page_length' => '25'])->assertRedirect();
        $this->assertSame('Asia/Makassar', Waktu::zona());
        $this->assertSame(25, Pengaturan::panjangTabel());
    }
}
