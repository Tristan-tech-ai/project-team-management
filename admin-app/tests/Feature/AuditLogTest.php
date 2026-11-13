<?php

namespace Tests\Feature;

use App\Http\Middleware\LogUserActions;
use App\Models\AuditLog;
use App\Support\UiSettings;
use App\Support\Dates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\Domain\BuildsTestData;
use Tests\TestCase;

/** Technical Guide 4.1: every Super Admin action is logged; only the Super Admin can open the log; passwords are never stored. */
class AuditLogTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    protected function tearDown(): void
    {
        Dates::forget();
        UiSettings::forget();
        parent::tearDown();
    }

    public function test_super_admin_actions_are_logged_without_passwords(): void
    {
        $admin = $this->user('super_admin', $this->company());
        $lastId = AuditLog::query()->max('id') ?? 0;

        $this->post('/login', ['email' => $admin->email, 'password' => 'test-password-123456'])->assertRedirect();
        $this->put('/settings/general', ['timezone' => 'Asia/Jakarta', 'table_page_length' => '25'])->assertRedirect();
        $this->post('/logout')->assertRedirect();

        $log = AuditLog::query()->where('id', '>', $lastId)->where('user_id', $admin->id)->orderBy('id')->get();
        $this->assertSame(['POST', 'PUT', 'POST'], $log->pluck('method')->all());
        $this->assertSame(['/login', '/settings/general', '/logout'], $log->pluck('path')->all());
        $this->assertSame('super_admin', $log[0]->role);
        $this->assertSame('admin', $log[0]->app);
        $this->assertSame('[disembunyikan]', $log[0]->input['password']);
        $this->assertSame($admin->email, $log[0]->input['email']);
        $this->assertSame('settings.general.update', $log[1]->route);
        $this->assertSame('Asia/Jakarta', $log[1]->input['timezone']);
        $this->assertSame(302, $log[1]->status);
        $this->assertArrayNotHasKey('_method', $log[1]->input);
        $this->assertStringNotContainsString('test-password-123456', json_encode($log->pluck('input')->all()));
    }

    public function test_rejected_actions_are_logged_and_get_requests_are_not(): void
    {
        $c = $this->company();
        $manager = $this->user('manager', $c);
        $lastId = AuditLog::query()->max('id') ?? 0;

        $this->actingAs($manager)->get('/dashboard')->assertOk();
        $this->actingAs($manager)->put('/settings/general', ['timezone' => 'UTC', 'table_page_length' => '10'])->assertForbidden();

        $log = AuditLog::query()->where('id', '>', $lastId)->where('user_id', $manager->id)->get();
        $this->assertCount(1, $log);
        $this->assertSame([403, 'manager'], [$log[0]->status, $log[0]->role]);
    }

    public function test_guests_are_not_logged(): void
    {
        $lastId = AuditLog::query()->max('id') ?? 0;
        $this->post('/login', ['email' => 'tidak-ada@uji.test', 'password' => 'wrong-password-123'])->assertSessionHasErrors('email');
        $this->assertSame(0, AuditLog::query()->where('id', '>', $lastId)->count());
    }

    public function test_input_sanitizer(): void
    {
        $result = LogUserActions::sanitize([
            'password_confirmation' => 'x', 'current_password' => 'y', 'api_token' => 'z', 'new_password' => 'w',
            'title' => str_repeat('a', 400), 'file' => UploadedFile::fake()->create('result.pdf', 10),
            'members' => [3, 4], 'details' => ['secret_key' => 'k', 'name' => 'Budi'],
        ]);
        foreach (['password_confirmation', 'current_password', 'api_token', 'new_password'] as $k) {
            $this->assertSame('[disembunyikan]', $result[$k]);
        }
        $this->assertSame(300, mb_strlen($result['title']));
        $this->assertSame('berkas: result.pdf', $result['file']);
        $this->assertSame([3, 4], $result['members']);
        $this->assertSame(['secret_key' => '[disembunyikan]', 'name' => 'Budi'], $result['details']);
    }

    public function test_activity_log_page_is_super_admin_only(): void
    {
        $c = $this->company();
        $admin = $this->user('super_admin', $c);
        $this->actingAs($admin)->put('/settings/general', ['timezone' => 'Asia/Makassar', 'table_page_length' => '25']);

        $html = $this->actingAs($admin)->get('/settings/audit?keyword='.urlencode($admin->email))->assertOk()->getContent();
        $this->assertStringContainsString($admin->name, $html);
        $this->assertStringContainsString('settings.general.update', $html);
        $this->actingAs($admin)->get('/settings/general')->assertOk()->assertSee(route('settings.audit'), false);
        $this->actingAs($admin)->get('/settings/audit?keyword=tidak-ada-yang-cocok-xyz')->assertOk()->assertSee('Tidak ada aksi yang cocok');

        foreach (['manager', 'member'] as $r) {
            $this->actingAs($this->user($r, $c))->get('/settings/audit')->assertForbidden();
        }
        $this->post('/logout');
        $this->get('/settings/audit')->assertRedirect('/login');
    }
}
