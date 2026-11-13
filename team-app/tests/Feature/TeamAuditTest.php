<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Technical Guide 4.1: Team App actions are logged to the same audit_logs, marked app team, without passwords. */
class TeamAuditTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    public function test_team_app_login_and_logout_are_logged(): void
    {
        $u = $this->user('member', $this->company());
        $initial = AuditLog::query()->max('id') ?? 0;

        $this->post('/login', ['email' => $u->email, 'password' => 'test-password-123456'])->assertRedirect();
        $this->get('/')->assertOk();
        $this->post('/logout')->assertRedirect();

        $log = AuditLog::query()->where('id', '>', $initial)->where('user_id', $u->id)->orderBy('id')->get();
        $this->assertSame(['/login', '/logout'], $log->pluck('path')->all());
        $this->assertSame(['team', 'team'], $log->pluck('app')->all());
        $this->assertSame('member', $log[0]->role);
        $this->assertSame('[disembunyikan]', $log[0]->input['password']);
    }
}
