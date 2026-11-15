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

    /**
     * I4 readable time next to a date. Deadlines: "hari ini 17.00", "besok 09.00", "3 hari lagi", "terlambat 2 hari".
     * Past events ($past): "baru saja", "2 jam lalu", "kemarin", "3 hari lalu".
     */
    public static function relative(?CarbonInterface $t, bool $past = false, ?CarbonInterface $now = null): ?string
    {
        $l = self::local($t);
        if ($l === null) {
            return null;
        }
        $n = Carbon::instance($now ?? now())->setTimezone(self::zone());
        $days = (int) $n->copy()->startOfDay()->diffInDays($l->copy()->startOfDay(), false);
        if ($past) {
            $minutes = (int) $l->diffInMinutes($n, false);

            return match (true) {
                $minutes < 2 => 'baru saja',
                $minutes < 60 => $minutes.' menit lalu',
                $days === 0 => intdiv($minutes, 60).' jam lalu',
                $days === -1 => 'kemarin',
                default => abs($days).' hari lalu',
            };
        }
        if ($l->lessThan($n)) {
            $hours = (int) $l->diffInHours($n);

            return $days === 0 ? 'terlambat '.max(1, $hours).' jam' : 'terlambat '.abs($days).' hari';
        }

        return match (true) {
            $days === 0 => 'hari ini '.$l->format('H.i'),
            $days === 1 => 'besok '.$l->format('H.i'),
            default => $days.' hari lagi',
        };
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
