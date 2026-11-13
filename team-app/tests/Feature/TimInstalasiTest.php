<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/** Revisi pembina + Petunjuk Teknis 2.3: migrasi dan seeder hanya dikelola Admin App; Team App tidak punya keduanya. */
class TimInstalasiTest extends TestCase
{
    public function test_team_app_tanpa_migrasi_seeder_dan_factory(): void
    {
        $this->assertDirectoryDoesNotExist(base_path('database'));
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);
        $skrip = json_encode($composer['scripts']);
        $this->assertStringNotContainsString('migrate', $skrip);
        $this->assertStringNotContainsString('db:seed', $skrip);
        $this->assertArrayNotHasKey('Database\\Seeders\\', $composer['autoload']['psr-4']);
        $this->assertArrayNotHasKey('Database\\Factories\\', $composer['autoload']['psr-4']);
    }

    /**
     * Dijalankan sebagai proses artisan sungguhan, karena Laravel tidak meneruskan event perintah ke listener saat tes unit
     * (dibuktikan merah dulu: lewat Artisan::call dan APP_ENV=testing, migrate tidak tertolak).
     * Perintah perusak (migrate:fresh, db:wipe) juga sudah diblokir DB::prohibitDestructiveCommands di AppServiceProvider.
     */
    public function test_perintah_migrasi_dan_seeder_ditolak_di_terminal(): void
    {
        foreach (['migrate', 'migrate:status', 'migrate:fresh', 'db:seed', 'db:wipe'] as $perintah) {
            // APP_ENV=testing (dari phpunit.xml) mematikan event perintah, jadi proses anak dijalankan seperti di server: local.
            $r = Process::path(base_path())->env(['APP_ENV' => 'local'])->timeout(60)->run([PHP_BINARY, 'artisan', $perintah, '--no-interaction']);
            $this->assertNotSame(0, $r->exitCode(), "{$perintah} seharusnya gagal di Team App");
            $this->assertStringContainsString('Team App tidak mengelola database', $r->output().$r->errorOutput(), $perintah);
        }
    }
}
