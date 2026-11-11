<?php

namespace App\Support;

/**
 * Kontrak kode status dan jenis. Kode disimpan dalam bahasa Inggris (SQL sumber), label UI bahasa Indonesia.
 * Dipakai identik oleh admin-app dan team-app (Petunjuk Teknis 9.1). SQL tidak punya CHECK, jadi daftar ini
 * adalah satu-satunya penjaga nilai yang sah dan divalidasi di setiap FormRequest.
 */
final class Kode
{
    public const TASK_STATUS = ['backlog' => 'Antrian', 'in_progress' => 'Dikerjakan', 'review' => 'Review', 'done' => 'Selesai'];

    public const PROJECT_STATUS = ['planned' => 'Direncanakan', 'active' => 'Berjalan', 'on_hold' => 'Ditunda', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public const PROJECT_TYPE = ['one_time' => 'Sekali jadi', 'recurring' => 'Berulang', 'ongoing' => 'Berkelanjutan'];

    public const PRIORITY = ['low' => 'Rendah', 'normal' => 'Normal', 'high' => 'Tinggi', 'urgent' => 'Mendesak'];

    public const TASK_TYPE = ['general' => 'Umum', 'development' => 'Development', 'design' => 'Desain', 'content' => 'Konten', 'marketing' => 'Marketing', 'research' => 'Riset'];

    public const ACTIVITY_TYPE = ['meeting' => 'Rapat', 'visit' => 'Kunjungan', 'revision' => 'Revisi', 'blocker' => 'Hambatan', 'other' => 'Lainnya'];

    public const ACTIVITY_STATUS = ['planned' => 'Direncanakan', 'done' => 'Selesai', 'cancelled' => 'Batal'];

    public const IMPACT_TYPE = ['none' => 'Tanpa dampak', 'minor' => 'Dampak kecil', 'delay' => 'Menggeser jadwal', 'blocked' => 'Menghambat'];

    public const SUBMISSION_STATUS = ['pending' => 'Menunggu review', 'approved' => 'Diterima', 'revision' => 'Perlu revisi'];

    public const SCHEDULE_STATUS = ['pending' => 'Menunggu persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'];

    public const PUBLICATION_STATUS = ['draft' => 'Draf', 'scheduled' => 'Terjadwal', 'published' => 'Terbit'];

    public const PERIOD_STATUS = ['active' => 'Berjalan', 'closed' => 'Ditutup'];

    public const RECURRENCE_UNIT = ['week' => 'Mingguan', 'month' => 'Bulanan'];

    /** Warna Bootstrap per status tugas: merah terlambat, kuning review, hijau selesai (Petunjuk Teknis 6.1). */
    public const TASK_STATUS_WARNA = ['backlog' => 'secondary', 'in_progress' => 'primary', 'review' => 'warning', 'done' => 'success'];

    public static function label(array $daftar, ?string $kode): string
    {
        return $kode === null ? '-' : ($daftar[$kode] ?? $kode);
    }

    public static function kunci(array $daftar): string
    {
        return implode(',', array_keys($daftar));
    }
}
