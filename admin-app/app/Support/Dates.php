<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Data is stored in UTC (config app.timezone); display follows the time zone in System Settings
 * (system_settings.timezone, default Asia/Makassar) as in Technical Guide 8.6.
 */
final class Dates
{
    private static ?string $zone = null;

    private const MONTHS = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public static function zone(): string
    {
        if (self::$zone === null) {
            $stored = SystemSetting::query()->where('setting_key', 'timezone')->value('setting_value');
            self::$zone = in_array($stored, timezone_identifiers_list(), true) ? $stored : 'Asia/Makassar';
        }

        return self::$zone;
    }

    public static function forget(): void
    {
        self::$zone = null;
    }

    public static function local(?CarbonInterface $t): ?Carbon
    {
        return $t === null ? null : Carbon::instance($t)->setTimezone(self::zone());
    }

    /** "18 Nov 2026". */
    public static function date(?CarbonInterface $t): string
    {
        $l = self::local($t);

        return $l === null ? '-' : $l->day.' '.self::MONTHS[$l->month].' '.$l->year;
    }

    /** "18 Nov 2026 14.30". */
    public static function dateTime(?CarbonInterface $t): string
    {
        $l = self::local($t);

        return $l === null ? '-' : self::date($t).' '.$l->format('H.i');
    }

    /** Value for a datetime-local input in the display time zone. */
    public static function forInput(?CarbonInterface $t): string
    {
        $l = self::local($t);

        return $l === null ? '' : $l->format('Y-m-d\TH:i');
    }

    /** Reads a date/time form input (display time zone) into UTC. */
    public static function fromInput(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value, self::zone())->utc();
    }
}
