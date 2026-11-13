<?php

namespace App\Support;

/**
 * Status and type code contract. Codes are stored in English (source SQL), UI labels are Indonesian.
 * Used identically by admin-app and team-app (Technical Guide 9.1). The SQL has no CHECK constraints, so this list
 * is the only guard of valid values and is validated in every FormRequest.
 */
final class Codes
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

    /** Bootstrap colour per task status: red overdue, yellow review, green done (Technical Guide 6.1). */
    public const TASK_STATUS_COLORS = ['backlog' => 'secondary', 'in_progress' => 'primary', 'review' => 'warning', 'done' => 'success'];

    public static function label(array $list, ?string $code): string
    {
        return $code === null ? '-' : ($list[$code] ?? $code);
    }

    public static function key(array $list): string
    {
        return implode(',', array_keys($list));
    }
}
