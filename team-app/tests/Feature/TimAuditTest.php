<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\Pembantu;
use Tests\TestCase;

/** Petunjuk Teknis 4.1: aksi di Team App ikut dicatat ke audit_logs yang sama, bertanda app team, tanpa sandi. */
class TimAuditTest extends TestCase
{
    use DatabaseTransactions, Pembantu;

    public function test_masuk_dan_keluar_di_team_app_dicatat(): void
    {
        $u = $this->pengguna('member', $this->company());
        $awal = AuditLog::query()->max('id') ?? 0;

        $this->post('/masuk', ['email' => $u->email, 'password' => 'sandi-uji-123456'])->assertRedirect();
        $this->get('/')->assertOk();
        $this->post('/keluar')->assertRedirect();

        $log = AuditLog::query()->where('id', '>', $awal)->where('user_id', $u->id)->orderBy('id')->get();
        $this->assertSame(['/masuk', '/keluar'], $log->pluck('path')->all());
        $this->assertSame(['team', 'team'], $log->pluck('app')->all());
        $this->assertSame('member', $log[0]->role);
        $this->assertSame('[disembunyikan]', $log[0]->input['password']);
    }
}
