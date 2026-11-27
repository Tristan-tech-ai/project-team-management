<?php

namespace Tests\Domain;

use App\Support\EnvFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * ptm:setup writes .env and then runs its next step in a child process. The child must see the values just written, although
 * Laravel never lets .env override an existing variable and the child inherits what the parent loaded from the old file
 * (server install test, 27 Nov 2026: Team App kept DB_HOST=127.0.0.1 after copying the database settings from the Admin App).
 */
class EnvFileTest extends TestCase
{
    public function test_changed_keys_loaded_from_the_old_file_are_unset_for_the_child(): void
    {
        $changed = ['DB_HOST' => 'mysql', 'DB_PASSWORD' => 'new-secret', 'APP_KEY' => 'base64:abc', 'ADMIN_APP_URL' => 'https://admin.example.com'];
        $before = ['DB_HOST' => '127.0.0.1', 'DB_PASSWORD' => '', 'APP_KEY' => '', 'ADMIN_APP_URL' => null];
        // DB_HOST and DB_PASSWORD came from the old .env; APP_KEY was exported by the operator; ADMIN_APP_URL was never set.
        $current = ['DB_HOST' => '127.0.0.1', 'DB_PASSWORD' => '', 'APP_KEY' => 'base64:from-the-shell', 'argv' => ['artisan']];

        $this->assertSame(['DB_HOST' => false, 'DB_PASSWORD' => false], EnvFile::staleInChild($changed, $before, $current));
    }

    public function test_child_process_reads_the_new_value_from_the_file(): void
    {
        $dir = sys_get_temp_dir().'/ptm-envfile-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $file = new EnvFile($dir.'/.env');
        $file->set(['PTM_PROBE_HOST' => 'old-host']);
        $script = $dir.'/read.php';
        file_put_contents($script, '<?php $v = getenv("PTM_PROBE_HOST"); if ($v === false) { foreach (file(__DIR__."/.env") as $l) { if (str_starts_with($l, "PTM_PROBE_HOST=")) { $v = trim(substr($l, 15)); } } } echo $v;');
        // What Laravel's Dotenv does with the old file in the parent: the value goes to putenv(), $_ENV and $_SERVER.
        putenv('PTM_PROBE_HOST=old-host');
        $_ENV['PTM_PROBE_HOST'] = $_SERVER['PTM_PROBE_HOST'] = 'old-host';
        try {
            $before = ['PTM_PROBE_HOST' => $file->get('PTM_PROBE_HOST')];
            $file->set(['PTM_PROBE_HOST' => 'new-host']);
            $inherited = new Process([PHP_BINARY, $script]);
            $inherited->run();
            $this->assertSame('old-host', $inherited->getOutput(), 'without the fix the child keeps the inherited old value');

            $fixed = new Process([PHP_BINARY, $script], null, EnvFile::staleInChild(['PTM_PROBE_HOST' => 'new-host'], $before, EnvFile::processVariables()));
            $fixed->run();
            $this->assertSame('new-host', $fixed->getOutput());
        } finally {
            putenv('PTM_PROBE_HOST');
            unset($_ENV['PTM_PROBE_HOST'], $_SERVER['PTM_PROBE_HOST']);
            @unlink($script);
            @unlink($dir.'/.env');
            @rmdir($dir);
        }
    }
}
