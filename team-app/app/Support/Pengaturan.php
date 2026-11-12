<?php

namespace App\Support;

use App\Models\SystemSetting;

/** Parameter UI dari Pengaturan Sistem (system_settings) yang dipakai kedua app. */
final class Pengaturan
{
    private static ?int $panjangTabel = null;

    /** Baris per halaman tabel DataTables (10, 25, 50, 100; bawaan 25). */
    public static function panjangTabel(): int
    {
        if (self::$panjangTabel === null) {
            $nilai = (int) SystemSetting::query()->where('setting_key', 'table_page_length')->value('setting_value');
            self::$panjangTabel = in_array($nilai, [10, 25, 50, 100], true) ? $nilai : 25;
        }

        return self::$panjangTabel;
    }

    public static function lupakan(): void
    {
        self::$panjangTabel = null;
    }
}
