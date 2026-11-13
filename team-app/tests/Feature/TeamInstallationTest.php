<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** Supervisor revision + Technical Guide 2.3: migrations and seeders are managed only by the Admin App; the Team App has neither. */
class TeamInstallationTest extends TestCase
{
    public function test_team_app_has_no_migrations_seeders_or_factories(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('database'));
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $script = json_encode($composer['scripts']);
        $this->assertStringNotContainsString('migrate', $script);
        $this->assertStringNotContainsString('db:seed', $script);
        $this->assertArrayNotHasKey('Database\\Seeders\\', $composer['autoload']['psr-4']);
        $this->assertArrayNotHasKey('Database\\Factories\\', $composer['autoload']['psr-4']);
    }

    /**
     * Runs as a real artisan process, because Laravel does not forward command events to listeners during unit tests
     * (proven red first: through Artisan::call and APP_ENV=testing, migrate was not refused).
     * Destructive commands (migrate:fresh, db:wipe) are also blocked by DB::prohibitDestructiveCommands in AppServiceProvider.
     */
    public function test_migration_and_seeder_commands_are_refused_in_terminal(): void
    {
        foreach (['migrate', 'migrate:status', 'migrate:fresh', 'db:seed', 'db:wipe'] as $command) {
            // APP_ENV=testing (from phpunit.xml) disables command events, so the child process runs like on a server: local.
            $r = Process::path(base_path())->env(['APP_ENV' => 'local'])->timeout(60)->run([PHP_BINARY, 'artisan', $command, '--no-interaction']);
            $this->assertNotSame(0, $r->exitCode(), "{$command} must fail in the Team App");
            $this->assertStringContainsString('Team App tidak mengelola database', $r->output().$r->errorOutput(), $command);
        }
    }
}
