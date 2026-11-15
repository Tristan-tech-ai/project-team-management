<?php

namespace App\Console\Commands;

use App\Support\DemoAccounts;
use App\Support\EnvFile;
use Database\Seeders\ShowcaseSeeder;
use Database\Seeders\UatScenarioSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * One-time installation of the Admin App so a fresh copy is ready to use:
 * .env with an application key, database tables (Laravel migrations + the module SQL, imported once),
 * initial data, demo accounts for every role, and sample agency data (local only). Safe to run again.
 */
class SetupCommand extends Command
{
    protected $signature = 'ptm:setup {--no-sample : Skip the sample agency data} {--stage=env : Internal step}';

    protected $description = 'Siapkan Admin App sekali jalan: .env, tabel, data awal, akun demo, dan data contoh';

    /** Database password of the MySQL container in docker-compose.yml when the root .env does not set one. */
    public const DOCKER_DB_PASSWORD = 'ptm-local';

    public function handle(): int
    {
        return $this->option('stage') === 'data' ? $this->prepareData() : $this->prepareEnvironment();
    }

    private function prepareEnvironment(): int
    {
        $this->newLine();
        $this->line('<options=bold>Menyiapkan Admin App</>');
        $env = new EnvFile(base_path('.env'));
        if (! $env->exists()) {
            copy(base_path('.env.example'), base_path('.env'));
            $this->line('  .env dibuat dari .env.example');
        }
        $set = [];
        if (! $env->get('APP_KEY')) {
            $set['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }
        if ($env->get('APP_ENV') === 'local') {
            foreach (['INITIAL_ADMIN_PASSWORD', 'DEMO_SEED_PASSWORD'] as $key) {
                if (! $env->get($key)) {
                    $set[$key] = DemoAccounts::DEFAULT_PASSWORD;
                }
            }
            if (! $env->get('DB_PASSWORD') && $env->get('DB_PORT') === '33061') {
                $set['DB_PASSWORD'] = (new EnvFile(base_path('../.env')))->get('MYSQL_PASSWORD') ?: self::DOCKER_DB_PASSWORD;
            }
            if ($env->get('PTM_DEMO_LOGIN') !== 'true' && $env->get('PTM_DEMO_LOGIN') !== 'false') {
                $set['PTM_DEMO_LOGIN'] = 'true';
            }
            if ($env->get('PTM_DEMO_LOGIN') === 'false' && ($set['INITIAL_ADMIN_PASSWORD'] ?? null)) {
                // A fresh local copy: offer the quick sign-in buttons on the login page.
                $set['PTM_DEMO_LOGIN'] = 'true';
            }
        }
        if ($set) {
            $env->set($set);
            $this->line('  .env dilengkapi: '.implode(', ', array_keys($set)));
        }

        // The next step runs in a new process so the values just written to .env are loaded.
        $process = new Process(array_values(array_filter([PHP_BINARY, 'artisan', 'ptm:setup', '--stage=data', $this->option('no-sample') ? '--no-sample' : null])), base_path());
        $process->setTimeout(null);
        $process->run(fn ($type, $out) => $this->output->write($out));

        return (int) $process->getExitCode();
    }

    private function prepareData(): int
    {
        $connection = config('database.default');
        $host = config("database.connections.$connection.host").':'.config("database.connections.$connection.port");
        $this->line("  [1/4] Menghubungi database {$host} ...");
        if (! $this->waitForDatabase()) {
            $this->error("  Database di {$host} belum bisa dihubungi.");
            $this->line('  Nyalakan MySQL dulu (dari folder utama: docker compose up -d), atau isi DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD di admin-app/.env, lalu jalankan lagi php artisan ptm:setup.');

            return self::FAILURE;
        }

        $this->line('  [2/4] Membuat tabel ...');
        $this->callSilently('migrate', ['--force' => true]);
        if (Schema::hasTable('companies')) {
            $this->line('        Tabel modul sudah ada, impor SQL dilewati.');
        } else {
            $count = $this->importModuleSql(base_path('../docs/project_team_management_laravel.sql'));
            $this->line("        SQL modul diimpor: {$count} pernyataan.");
        }

        $this->line('  [3/4] Mengisi data awal ...');
        $this->callSilently('db:seed', ['--force' => true]);
        $local = app()->environment('local');
        if ($local && ! $this->option('no-sample')) {
            $this->line('        Data contoh agensi (project, task, aktivitas) ...');
            $this->callSilently('db:seed', ['--class' => UatScenarioSeeder::class, '--force' => true]);
            $this->callSilently('db:seed', ['--class' => ShowcaseSeeder::class, '--force' => true]);
        }

        $this->line('  [4/4] Selesai.');
        $this->newLine();
        $this->printAccounts($local);

        return self::SUCCESS;
    }

    private function waitForDatabase(): bool
    {
        for ($i = 0; $i < 30; $i++) {
            try {
                DB::connection()->getPdo();

                return true;
            } catch (Throwable) {
                DB::purge();
                sleep(3);
            }
        }

        return false;
    }

    /** Same rules as docs/tools/import-sql.php: one statement at a time, stop at the first failure. */
    private function importModuleSql(string $path): int
    {
        if (! is_file($path)) {
            throw new \RuntimeException("Berkas SQL modul tidak ditemukan: {$path}");
        }
        $pdo = DB::connection()->getPdo();
        $count = 0;
        foreach (preg_split('/;\s*\n/', (string) file_get_contents($path)) as $statement) {
            $clean = trim((string) preg_replace('/^\s*--.*$/m', '', $statement));
            if ($clean !== '') {
                $pdo->exec($clean);
                $count++;
            }
        }

        return $count;
    }

    private function printAccounts(bool $local): void
    {
        $adminPassword = (string) config('app.initial_admin_password');
        $demoPassword = (string) config('app.demo_password');
        $show = fn (string $value, string $key) => $value === DemoAccounts::DEFAULT_PASSWORD ? $value : "(sandi di .env: {$key})";
        $adminUrl = rtrim((string) config('app.url'), '/');
        $teamUrl = rtrim((string) config('app.team_url'), '/');
        $rows = [['Super Admin', (string) config('app.initial_admin_email'), $show($adminPassword, 'INITIAL_ADMIN_PASSWORD'), 'Admin App']];
        if ($local) {
            $rows[] = ['Manager', DemoAccounts::MANAGER_EMAIL, $show($demoPassword, 'DEMO_SEED_PASSWORD'), 'Admin App dan Team App'];
            $rows[] = ['Member', DemoAccounts::MEMBER_EMAIL, $show($demoPassword, 'DEMO_SEED_PASSWORD'), 'Team App (juga Admin App)'];
            if (! $this->option('no-sample')) {
                $rows[] = ['Manager (tim contoh)', 'radit@example.test', $show($demoPassword, 'DEMO_SEED_PASSWORD'), 'Admin App dan Team App'];
                $rows[] = ['Member (tim contoh)', 'ayu@example.test, binda@, putri@, dika@, raka@', $show($demoPassword, 'DEMO_SEED_PASSWORD'), 'Team App'];
            }
        }
        $this->line('<options=bold>Akun untuk mencoba</>');
        $this->table(['Peran', 'Email', 'Sandi', 'Masuk di'], $rows);
        $this->line("  Admin App: {$adminUrl}   Team App: {$teamUrl}");
        $this->line('  Jalankan kedua app dari folder utama: start.ps1 (Windows) atau ./start.sh, atau php artisan serve --port=8101 di admin-app dan --port=8102 di team-app.');
        if (DemoAccounts::enabled()) {
            $this->line('  Di halaman masuk lokal ada tombol "Coba sebagai" untuk akun di atas.');
        }
    }
}
