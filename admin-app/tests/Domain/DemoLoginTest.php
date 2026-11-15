<?php

namespace Tests\Domain;

use App\Models\User;
use App\Support\DemoAccounts;
use App\Support\EnvFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** U1 ready-to-use: local demo accounts on the login page (never outside APP_ENV=local) and the .env helper used by ptm:setup. */
class DemoLoginTest extends TestCase
{
    use DatabaseTransactions, BuildsTestData;

    private function demoUser(string $email, string $role): User
    {
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => ucfirst($role).' Demo', 'password' => 'test-password-123456']);
        DB::table('user_profiles')->updateOrInsert(['user_id' => $user->id], ['is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

        return $user;
    }

    private function enableDemo(string $env): void
    {
        $this->app['env'] = $env;
        config(['app.demo_login' => true, 'app.demo_password' => 'demo-password-123', 'app.initial_admin_email' => 'admin.demo@uji.test',
            'app.initial_admin_password' => 'admin-password-123']);
        $this->demoUser('admin.demo@uji.test', 'super_admin');
        $this->demoUser(DemoAccounts::MANAGER_EMAIL, 'manager');
        $this->demoUser(DemoAccounts::MEMBER_EMAIL, 'member');
    }

    public function test_local_login_page_offers_demo_accounts(): void
    {
        $this->enableDemo('local');
        $isAdminApp = Route::has('management.index');
        $page = $this->get('/login')->assertOk()->assertSee('Coba sebagai')
            ->assertSee(DemoAccounts::MANAGER_EMAIL)->assertSee(DemoAccounts::MEMBER_EMAIL)->assertSee('data-demo-password', false);
        // The Super Admin works in the Admin App only.
        $isAdminApp ? $page->assertSee('admin.demo@uji.test') : $page->assertDontSee('admin.demo@uji.test');
        $this->app['env'] = 'testing';
    }

    public function test_demo_accounts_never_shown_outside_local(): void
    {
        foreach (['production', 'testing'] as $env) {
            $this->enableDemo($env);
            $this->assertSame([], DemoAccounts::forLogin(true));
            $this->get('/login')->assertOk()->assertDontSee('Coba sebagai')->assertDontSee('data-demo-password', false);
        }
        $this->app['env'] = 'local';
        config(['app.demo_login' => false]);
        $this->assertSame([], DemoAccounts::forLogin(true), 'PTM_DEMO_LOGIN=false hides the buttons');
        $this->app['env'] = 'testing';
    }

    public function test_inactive_demo_account_is_not_offered(): void
    {
        $this->enableDemo('local');
        $member = User::query()->where('email', DemoAccounts::MEMBER_EMAIL)->first();
        DB::table('user_profiles')->where('user_id', $member->id)->update(['is_active' => 0]);
        $this->assertNotContains(DemoAccounts::MEMBER_EMAIL, array_column(DemoAccounts::forLogin(true), 'email'));
        $this->app['env'] = 'testing';
    }

    public function test_env_file_reads_and_writes_keys(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "APP_NAME=\"PTM Admin\"\nAPP_KEY=\nDB_PASSWORD=\n# comment\n");
        $env = new EnvFile($path);
        $this->assertSame('PTM Admin', $env->get('APP_NAME'));
        $this->assertSame('', $env->get('APP_KEY'));
        $this->assertNull($env->get('MISSING'));
        $env->set(['APP_KEY' => 'base64:abc=', 'DB_PASSWORD' => 'with space', 'NEW_KEY' => 'x']);
        $this->assertSame('base64:abc=', $env->get('APP_KEY'));
        $this->assertSame('with space', $env->get('DB_PASSWORD'));
        $this->assertSame('x', $env->get('NEW_KEY'));
        $this->assertStringContainsString("# comment\n", file_get_contents($path));
        $this->assertSame(1, substr_count(file_get_contents($path), 'APP_KEY='));
        unlink($path);
    }
}
