<?php

namespace App\Support;

use App\Models\SystemSetting;

/** UI parameters from System Settings (system_settings) used by both apps. */
final class UiSettings
{
    private static ?int $tablePageLength = null;

    /** DataTables rows per page (10, 25, 50, 100; default 25). */
    public static function tablePageLength(): int
    {
        if (self::$tablePageLength === null) {
            $length = (int) SystemSetting::query()->where('setting_key', 'table_page_length')->value('setting_value');
            self::$tablePageLength = in_array($length, [10, 25, 50, 100], true) ? $length : 25;
        }

        return self::$tablePageLength;
    }

    public static function forget(): void
    {
        self::$tablePageLength = null;
    }
}
