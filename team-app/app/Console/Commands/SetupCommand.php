<?php

namespace App\Console\Commands;

use App\Support\DemoAccounts;
use App\Support\EnvFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * One-time preparation of the Team App: .env with its own application key and the same database as the Admin App
 * (copied from ../admin-app/.env when present). The Team App never creates tables or data (Technical Guide 2.3).
 */
class SetupCommand extends Command
{
    protected $signature = 'ptm:setup {--stage=env : Internal step}';

    protected $description = 'Siapkan Team App sekali jalan: .env dan sambungan ke database yang sama dengan Admin App';

    /** Values shared with the Admin App: the same database and the same local demo accounts. */
    private const SHARED_KEYS = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DEMO_SEED_PASSWORD', 'PTM_DEMO_LOGIN'];

    public function handle(): int
    {
        return $this->option('stage') === 'check' ? $this->check() : $this->prepareEnvironment();
    }

    private function prepareEnvironment(): int
    {
        $this->newLine();
        $this->line('<options=bold>Menyiapkan Team App</>');
        $env = new EnvFile(base_path('.env'));
        if (! $env->exists()) {
            copy(base_path('.env.example'), base_path('.env'));
            $this->line('  .env dibuat dari .env.example');
        }
        $set = [];
        if (! $env->get('APP_KEY')) {
            $set['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }
        $admin = new EnvFile(base_path('../admin-app/.env'));
        if ($admin->exists()) {
            foreach (self::SHARED_KEYS as $key) {
                $value = $admin->get($key);
                if ($value !== null && $value !== '' && $env->get($key) !== $value) {
                    $set[$key] = $value;
                }
            }
        }
        if ($set) {
            $env->set($set);
            $this->line('  .env dilengkapi: '.implode(', ', array_keys($set)));
        }

        $process = new Process([PHP_BINARY, 'artisan', 'ptm:setup', '--stage=check'], base_path());
        $process->setTimeout(null);
        $process->run(fn ($type, $out) => $this->output->write($out));

        return (int) $process->getExitCode();
    }

    private function check(): int
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $this->error('  Database belum bisa dihubungi. Jalankan php artisan ptm:setup di admin-app dulu, lalu ulangi di sini.');

            return self::FAILURE;
        }
        if (! Schema::hasTable('tasks') || ! Schema::hasTable('users')) {
            $this->error('  Tabel belum ada. Jalankan php artisan ptm:setup di admin-app dulu.');

            return self::FAILURE;
        }
        $this->line('  Database terhubung: tabel dan data dari Admin App siap dipakai.');
        $this->line('  Team App: '.rtrim((string) config('app.url'), '/').(DemoAccounts::enabled() ? '  (tombol "Coba sebagai" tersedia untuk Manager dan Member demo)' : ''));

        return self::SUCCESS;
    }
}
