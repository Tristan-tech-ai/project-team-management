<?php

/*
 * Copies the shared domain code from admin-app to team-app (Technical Guide 9.1: status, progress, permission and
 * deadline logic must be identical in both apps, with the same tests).
 * Usage:  php docs/tools/sync-domain.php           (copy)
 *         php docs/tools/sync-domain.php --check   (check only; exits 1 when something differs)
 * The SharedDomainTest test in both apps reads the same list and fails when a copy differs.
 */

const SHARED = [
    'app/Models', 'app/Policies', 'app/Services', 'app/Support', 'app/Http/Requests/Work', 'app/Http/Middleware',
    'app/Http/Controllers/Shared', 'app/Http/Controllers/Auth', 'app/Http/Controllers/AccountController.php', 'app/Http/Controllers/Controller.php',
    'app/Providers/AppServiceProvider.php', 'lang/id', 'resources/icons', 'resources/views/shared', 'resources/views/errors',
    'resources/views/components/icon.blade.php', 'resources/views/components/badge.blade.php', 'resources/views/components/field.blade.php',
    'resources/views/components/confirm.blade.php', 'resources/views/components/hint.blade.php', 'resources/views/components/task-panel.blade.php', 'resources/views/components/layouts/guest.blade.php', 'resources/views/auth', 'resources/views/account/form.blade.php',
    'resources/js/core.js', 'resources/js/table.js', 'resources/js/kanban.js', 'resources/js/calendar.js', 'resources/css/app.css', 'tests/Domain',
];

/** @return list<string> relative paths of the files under $path (a file or a folder). */
function files(string $root, string $path): array
{
    $full = $root.'/'.$path;
    if (is_file($full)) {
        return [$path];
    }
    if (! is_dir($full)) {
        return [];
    }
    $result = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($full, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $result[] = $path.'/'.str_replace('\\', '/', substr($f->getPathname(), strlen($full) + 1));
    }
    sort($result);

    return $result;
}

/** @return array<string, string> relative path => sha256 */
function fingerprint(string $root): array
{
    $hashes = [];
    foreach (SHARED as $path) {
        foreach (files($root, $path) as $f) {
            $hashes[$f] = hash_file('sha256', $root.'/'.$f);
        }
    }

    return $hashes;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    $repo = dirname(__DIR__, 2);
    $source = $repo.'/admin-app';
    $target = $repo.'/team-app';
    $checkOnly = in_array('--check', $argv, true);
    $a = fingerprint($source);
    $b = fingerprint($target);
    $different = array_keys(array_filter($a, fn ($v, $k) => ($b[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
    $extra = array_diff(array_keys($b), array_keys($a));
    if ($checkOnly) {
        foreach ($different as $f) {
            echo "DIFFERENT {$f}\n";
        }
        foreach ($extra as $f) {
            echo "EXTRA     {$f} (only in team-app)\n";
        }
        echo ($different || $extra) ? "shared code is NOT identical\n" : 'shared code identical: '.count($a)." files\n";
        exit(($different || $extra) ? 1 : 0);
    }
    foreach ($different as $f) {
        @mkdir(dirname($target.'/'.$f), 0777, true);
        copy($source.'/'.$f, $target.'/'.$f);
        echo "copy {$f}\n";
    }
    foreach ($extra as $f) {
        // Shared files that no longer exist in admin-app are removed from team-app so the copies stay identical.
        unlink($target.'/'.$f);
        echo "remove {$f}\n";
    }
    echo count($different).' copied, '.count($extra).' removed, '.count($a)." shared files\n";
}
