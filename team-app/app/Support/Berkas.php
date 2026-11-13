<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Penyimpanan berkas bersama Admin App dan Team App (disk `bersama`, root di storage admin-app).
 * Berkas lama yang tersimpan sebelum disk bersama ada (disk local/public milik app masing-masing) tetap dicari sebagai cadangan.
 */
final class Berkas
{
    public const DISK = 'bersama';

    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /** Disk tempat berkas ini benar-benar ada, atau null bila tidak ditemukan. */
    public static function cari(?string $disk, ?string $path): ?Filesystem
    {
        if ($path === null || $path === '') {
            return null;
        }
        foreach (array_unique([self::DISK, $disk ?: 'local']) as $nama) {
            if (in_array($nama, [self::DISK, 'local', 'public'], true) && Storage::disk($nama)->exists($path)) {
                return Storage::disk($nama);
            }
        }

        return null;
    }
}
