<?php

namespace App\Support;

/**
 * Minimal reader/writer for a Laravel .env file, used by the one-time setup command.
 * Existing lines keep their position; new keys are appended at the end.
 */
final class EnvFile
{
    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key): ?string
    {
        if (! $this->exists()) {
            return null;
        }
        foreach (preg_split('/\R/', (string) file_get_contents($this->path)) as $line) {
            if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=(.*)$/', $line, $m)) {
                $value = trim($m[1]);
                if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                    $value = substr($value, 1, -1);
                }

                return $value;
            }
        }

        return null;
    }

    /** @param array<string, string> $values */
    public function set(array $values): void
    {
        $content = $this->exists() ? (string) file_get_contents($this->path) : '';
        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^\s*'.preg_quote($key, '/').'\s*=.*$/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, fn () => $line, $content, 1);
            } else {
                $content = rtrim($content, "\r\n")."\n".$line."\n";
            }
        }
        file_put_contents($this->path, $content);
    }

    /**
     * Environment for the artisan process that runs after set(). Laravel never lets .env override a variable that already exists,
     * and a child process inherits the variables this process loaded from the old file, so the child would keep the old values.
     * Every changed key whose current value came from the old file is unset for the child (false, as Symfony Process expects) so the
     * child reads the new value; a value set in the real environment (different from the old file) is kept, because it wins anyway.
     *
     * @param  array<string, string>  $changed  the values passed to set()
     * @param  array<string, ?string>  $before  the values in the file before set()
     * @param  array<string, mixed>  $current  this process's variables (getenv(), $_ENV, $_SERVER)
     * @return array<string, false>
     */
    public static function staleInChild(array $changed, array $before, array $current): array
    {
        $unset = [];
        foreach (array_keys($changed) as $key) {
            if (array_key_exists($key, $current) && is_scalar($current[$key]) && (string) $current[$key] === (string) ($before[$key] ?? '')) {
                $unset[$key] = false;
            }
        }

        return $unset;
    }

    /** @return array<string, mixed> the variables of the running process, as Laravel sees them */
    public static function processVariables(): array
    {
        return array_filter(getenv() + $_ENV + $_SERVER, 'is_scalar');
    }

    private static function quote(string $value): string
    {
        return preg_match('/[\s#"\'$]/', $value) ? '"'.addcslashes($value, '"\\').'"' : $value;
    }
}
