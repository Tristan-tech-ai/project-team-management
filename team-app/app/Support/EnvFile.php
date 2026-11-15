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

    private static function quote(string $value): string
    {
        return preg_match('/[\s#"\'$]/', $value) ? '"'.addcslashes($value, '"\\').'"' : $value;
    }
}
