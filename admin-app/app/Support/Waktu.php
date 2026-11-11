<?php

namespace App\Support;

use App\Models\SystemSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Data disimpan dalam UTC (config app.timezone), tampilan mengikuti zona waktu di Pengaturan Sistem
 * (system_settings.timezone, bawaan Asia/Makassar) sesuai Petunjuk Teknis 8.6.
 */
final class Waktu
{
    private static ?string $zona = null;

    private const BULAN = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    public static function zona(): string
    {
        if (self::$zona === null) {
            $nilai = SystemSetting::query()->where('setting_key', 'timezone')->value('setting_value');
            self::$zona = in_array($nilai, timezone_identifiers_list(), true) ? $nilai : 'Asia/Makassar';
        }

        return self::$zona;
    }

    public static function lupakan(): void
    {
        self::$zona = null;
    }

    public static function lokal(?CarbonInterface $t): ?Carbon
    {
        return $t === null ? null : Carbon::instance($t)->setTimezone(self::zona());
    }

    /** "18 Nov 2026". */
    public static function tanggal(?CarbonInterface $t): string
    {
        $l = self::lokal($t);

        return $l === null ? '-' : $l->day.' '.self::BULAN[$l->month].' '.$l->year;
    }

    /** "18 Nov 2026 14.30". */
    public static function tanggalJam(?CarbonInterface $t): string
    {
        $l = self::lokal($t);

        return $l === null ? '-' : self::tanggal($t).' '.$l->format('H.i');
    }

    /** Nilai untuk input datetime-local di zona tampilan. */
    public static function untukInput(?CarbonInterface $t): string
    {
        $l = self::lokal($t);

        return $l === null ? '' : $l->format('Y-m-d\TH:i');
    }

    /** Membaca isian tanggal/jam dari formulir (zona tampilan) menjadi UTC. */
    public static function dariInput(?string $nilai): ?Carbon
    {
        if ($nilai === null || trim($nilai) === '') {
            return null;
        }

        return Carbon::parse($nilai, self::zona())->utc();
    }
}
