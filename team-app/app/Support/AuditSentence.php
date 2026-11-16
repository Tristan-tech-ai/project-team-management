<?php

namespace App\Support;

use App\Models\AuditLog;

/**
 * I10: an audit log row as a sentence people can read ("menambah task Desain feed", "menerima hasil") instead of a method and an
 * address. The technical record stays in the database and is still shown as detail on request.
 */
final class AuditSentence
{
    /** route name => [verb phrase, input key that names the thing] */
    private const ACTIONS = [
        'login' => ['masuk', null],
        'logout' => ['keluar', null],
        'projects.store' => ['membuat project', 'name'],
        'projects.update' => ['mengubah project', 'name'],
        'projects.destroy' => ['menghapus project', null],
        'projects.team.update' => ['mengatur tim project', null],
        'projects.periods.store' => ['membuka periode baru project', null],
        'projects.tasks.store' => ['menambah task', 'title'],
        'projects.activities.store' => ['mencatat aktivitas', 'title'],
        'tasks.update' => ['mengubah task', 'title'],
        'tasks.destroy' => ['menghapus task', null],
        'tasks.assignees' => ['mengatur PIC dan pelaksana task', null],
        'tasks.subtasks.store' => ['menambah checklist', 'title'],
        'tasks.submissions.store' => ['mengirim hasil task', null],
        'tasks.time.store' => ['mencatat waktu kerja', null],
        'schedule-changes.store' => ['mengajukan geser deadline', null],
        'companies.store' => ['menambah company', 'name'], 'companies.update' => ['mengubah company', 'name'], 'companies.destroy' => ['menghapus company', null],
        'clients.store' => ['menambah client', 'name'], 'clients.update' => ['mengubah client', 'name'], 'clients.destroy' => ['menghapus client', null],
        'divisions.store' => ['menambah divisi', 'name'], 'divisions.update' => ['mengubah divisi', 'name'], 'divisions.toggle' => ['mengubah status divisi', null],
        'positions.store' => ['menambah jabatan', 'name'], 'positions.update' => ['mengubah jabatan', 'name'], 'positions.toggle' => ['mengubah status jabatan', null],
        'members.store' => ['menambah anggota', 'name'], 'members.update' => ['mengubah anggota', 'name'], 'members.destroy' => ['menonaktifkan anggota', null],
        'templates.projects.store' => ['membuat template project', 'name'], 'templates.projects.update' => ['mengubah template project', 'name'],
        'templates.tasks.store' => ['membuat template task', 'name'], 'templates.tasks.update' => ['mengubah template task', 'name'],
        'settings.roles.update' => ['mengubah hak akses role', null],
        'settings.menus.update' => ['mengubah pengaturan menu', null],
        'settings.menus.groups.store' => ['menambah kelompok menu', 'title'],
        'settings.menus.groups.destroy' => ['menghapus kelompok menu', null],
        'settings.general.update' => ['mengubah pengaturan sistem', null],
        'account.update' => ['mengubah profil', null],
        'account.password' => ['mengganti kata sandi', null],
    ];

    public static function describe(AuditLog $log): string
    {
        $input = is_array($log->input) ? $log->input : [];
        $route = (string) $log->route;
        $phrase = match ($route) {
            'tasks.status' => 'memindah task ke '.Codes::label(Codes::TASK_STATUS, $input['status'] ?? null),
            'subtasks.toggle' => ! empty($input['done']) ? 'mencentang checklist' : 'membuka lagi checklist',
            'submissions.review' => ($input['decision'] ?? '') === 'approved' ? 'menerima hasil task' : 'meminta revisi hasil task',
            'schedule-changes.decision' => ($input['decision'] ?? '') === 'approved' ? 'menyetujui geser deadline' : 'menolak geser deadline',
            // I13 bulk action: "mengganti PIC 3 task sekaligus".
            'tasks.bulk' => (['start' => 'memulai', 'pic' => 'mengganti PIC', 'assignee' => 'menambah pelaksana di'][$input['action'] ?? ''] ?? 'mengubah')
                .' '.count((array) ($input['ids'] ?? [])).' task sekaligus',
            default => null,
        };
        if ($phrase === null) {
            [$phrase, $nameKey] = self::ACTIONS[$route] ?? [self::fallback($log), null];
            if ($nameKey && is_string($input[$nameKey] ?? null) && trim($input[$nameKey]) !== '') {
                $phrase .= ' '.mb_strimwidth(trim($input[$nameKey]), 0, 60, '...');
            }
        }
        if ($route === 'tasks.time.store' && is_numeric($input['minutes_spent'] ?? $input['minutes'] ?? null)) {
            $phrase .= ' '.(int) ($input['minutes_spent'] ?? $input['minutes']).' menit';
        }

        return $phrase;
    }

    /** Outcome of the request in plain words. */
    public static function outcome(int $status): array
    {
        return match (true) {
            $status < 400 => ['Berhasil', 'ok'],
            $status === 403 => ['Ditolak: tanpa izin', 'late'],
            $status === 419 => ['Sesi kedaluwarsa', 'review'],
            $status === 422 => ['Isian belum benar', 'review'],
            $status === 429 => ['Terlalu sering dicoba', 'review'],
            default => ['Gagal', 'late'],
        };
    }

    private static function fallback(AuditLog $log): string
    {
        $verb = ['POST' => 'menyimpan', 'PUT' => 'mengubah', 'PATCH' => 'mengubah', 'DELETE' => 'menghapus'][strtoupper((string) $log->method)] ?? 'membuka';

        return $verb.' data di '.trim((string) $log->path, '/');
    }
}
