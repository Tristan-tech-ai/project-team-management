<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * File storage shared by the Admin App and the Team App (disk `shared`, rooted in the admin-app storage).
 * Files stored before the shared disk existed (each app's own local/public disk) are still looked up as a fallback.
 */
final class Files
{
    public const DISK = 'shared';

    /** Disk names written to task_attachments.storage_disk by earlier versions. */
    private const LEGACY_NAMES = ['bersama' => self::DISK];

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /** The disk where this file actually exists, or null when it cannot be found. */
    public static function find(?string $disk, ?string $path): ?Filesystem
    {
        if ($path === null || $path === '') {
            return null;
        }
        $disk = self::LEGACY_NAMES[$disk] ?? $disk;
        foreach (array_unique([self::DISK, $disk ?: 'local']) as $name) {
            if (in_array($name, [self::DISK, 'local', 'public'], true) && Storage::disk($name)->exists($path)) {
                return Storage::disk($name);
            }
        }

        return null;
    }
}
