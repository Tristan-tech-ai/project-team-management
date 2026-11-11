<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Metrik Management (Petunjuk Teknis 3.2, 8.4, 11.2) dihitung dengan query agregat, bukan per baris (tanpa N+1).
 * - Progress project = task utama selesai / task utama non-deleted (subtask tidak masuk penyebut).
 * - Terlambat = due_at < sekarang dan status bukan done, dihitung dinamis.
 * - Anggota unik (COUNT DISTINCT project_members.user_id) dibedakan dari jumlah penugasan (baris task_assignees).
 * - Deviasi = due_at terbaru dibanding baseline_due_at; ketepatan = selesai sebelum/sesudah deadline yang disetujui.
 */
class ManagementMetricsService
{
    public function kpi(User $user): array
    {
        $ids = Scope::projects($user)->select('projects.id');
        $aktif = DB::table('projects')->whereNull('deleted_at')->whereIn('id', $ids)->whereIn('status', ['planned', 'active', 'on_hold'])
            ->selectRaw('project_type, count(*) n')->groupBy('project_type')->pluck('n', 'project_type');
        $utama = DB::table('tasks')->whereNull('deleted_at')->whereNull('parent_id')->whereIn('project_id', $ids);

        return [
            'project_aktif' => (int) $aktif->sum(), 'per_tipe' => $aktif->map(fn ($n) => (int) $n)->all(),
            'task_utama' => (clone $utama)->count(),
            'task_berjalan' => (clone $utama)->where('status', 'in_progress')->count(),
            'terlambat' => (clone $utama)->where('status', '!=', 'done')->whereNotNull('due_at')->where('due_at', '<', now())->count(),
            'review' => (clone $utama)->where('status', 'review')->count(),
            'jadwal_menunggu' => DB::table('schedule_change_requests')->whereIn('project_id', $ids)->where('status', 'pending')->count(),
            'aktivitas_7_hari' => DB::table('project_activities')->whereIn('project_id', $ids)->where('created_at', '>=', now()->subDays(7))->count(),
            'anggota_unik' => (int) DB::table('project_members')->whereIn('project_id', $ids)->where('is_active', 1)->distinct()->count('user_id'),
            'penugasan' => DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->whereNull('tasks.deleted_at')->whereIn('tasks.project_id', $ids)->count(),
        ];
    }

    /**
     * Monitoring project: progress, anggota unik, divisi, terlambat, baseline vs due, kondisi.
     * Kondisi: blocked = ada aktivitas berdampak "Menghambat" yang belum selesai; delayed = due_at project lewat atau ada task utama terlambat;
     * at_risk = due_at bergeser dari baseline atau ada pengajuan jadwal menunggu; selain itu on_track. Project selesai/batal = completed/cancelled.
     *
     * @return Collection<int, object>
     */
    public function monitoring(User $user, array $filter = []): Collection
    {
        $q = Scope::projects($user)->from('projects')->whereNull('projects.deleted_at')
            ->leftJoin('companies', 'companies.id', '=', 'projects.company_id')
            ->leftJoin('clients', 'clients.id', '=', 'projects.client_id')
            ->leftJoin('users as pic', 'pic.id', '=', 'projects.pic_user_id')
            ->select('projects.id', 'projects.code', 'projects.name', 'projects.project_type', 'projects.status', 'projects.baseline_due_at', 'projects.due_at',
                'companies.name as company', 'clients.name as client', 'pic.name as pic')
            ->selectSub(DB::table('tasks')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->whereNull('parent_id')->whereNull('deleted_at'), 'task_utama')
            ->selectSub(DB::table('tasks')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->whereNull('parent_id')->whereNull('deleted_at')->where('status', 'done'), 'task_selesai')
            ->selectSub(DB::table('tasks')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->whereNull('parent_id')->whereNull('deleted_at')->where('status', '!=', 'done')->where('due_at', '<', now()), 'task_terlambat')
            ->selectSub(DB::table('project_members')->selectRaw('count(distinct user_id)')->whereColumn('project_id', 'projects.id')->where('is_active', 1), 'anggota')
            ->selectSub(DB::table('project_divisions')->selectRaw('count(distinct division_id)')->whereColumn('project_id', 'projects.id'), 'divisi')
            ->selectSub(DB::table('project_activities')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->where('impact_type', 'blocked')->where('status', '!=', 'done'), 'hambatan')
            ->selectSub(DB::table('schedule_change_requests')->selectRaw('count(*)')->whereColumn('project_id', 'projects.id')->where('status', 'pending'), 'jadwal_menunggu')
            ->orderByRaw("field(projects.status,'active','on_hold','planned','completed','cancelled')")->orderBy('projects.due_at');
        foreach (['project_type', 'status', 'company_id', 'client_id', 'pic_user_id'] as $k) {
            if (! empty($filter[$k])) {
                $q->where('projects.'.$k, $filter[$k]);
            }
        }

        return $q->get()->map(function ($p) {
            $p->progres = $p->task_utama ? (int) round($p->task_selesai * 100 / $p->task_utama) : 0;
            $p->kondisi = self::kondisi($p);

            return $p;
        });
    }

    public static function kondisi(object $p): string
    {
        if (in_array($p->status, ['completed', 'cancelled'], true)) {
            return $p->status;
        }
        if ((int) $p->hambatan > 0) {
            return 'blocked';
        }
        if (($p->due_at && \Illuminate\Support\Carbon::parse($p->due_at)->isPast()) || (int) $p->task_terlambat > 0) {
            return 'delayed';
        }
        if (($p->baseline_due_at && $p->due_at && (string) $p->baseline_due_at !== (string) $p->due_at) || (int) $p->jadwal_menunggu > 0) {
            return 'at_risk';
        }

        return 'on_track';
    }

    public const KONDISI = ['on_track' => 'Sesuai jadwal', 'at_risk' => 'Perlu perhatian', 'delayed' => 'Terlambat', 'blocked' => 'Terhambat', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'];

    public const KONDISI_KELAS = ['on_track' => 'ok', 'at_risk' => 'risk', 'delayed' => 'telat', 'blocked' => 'blocked', 'completed' => 'done', 'cancelled' => 'netral'];

    /** Beban per anggota: penugasan aktif, terlambat, selesai, menit kerja tercatat, menit aktivitas yang dihadiri. */
    public function bebanAnggota(User $user, ?int $divisionId = null): Collection
    {
        $ids = Scope::projects($user)->select('projects.id');
        $q = DB::table('users')->join('user_profiles', 'user_profiles.user_id', '=', 'users.id')->where('user_profiles.is_active', 1)
            ->whereIn('users.id', DB::table('project_members')->select('user_id')->whereIn('project_id', $ids)->where('is_active', 1))
            ->select('users.id', 'users.name')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->selectRaw('count(*)')->whereColumn('task_assignees.user_id', 'users.id')
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->whereIn('tasks.project_id', $ids), 'aktif')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->selectRaw('count(*)')->whereColumn('task_assignees.user_id', 'users.id')
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->where('tasks.due_at', '<', now())->whereIn('tasks.project_id', $ids), 'terlambat')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')->selectRaw('count(*)')->whereColumn('task_assignees.user_id', 'users.id')
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', 'done')->whereIn('tasks.project_id', $ids), 'selesai')
            ->selectSub(DB::table('project_members')->selectRaw('count(distinct project_id)')->whereColumn('project_members.user_id', 'users.id')->where('is_active', 1)->whereIn('project_id', $ids), 'project')
            ->selectSub(DB::table('task_time_entries')->join('tasks', 'tasks.id', '=', 'task_time_entries.task_id')->selectRaw('coalesce(sum(minutes_spent),0)')->whereColumn('task_time_entries.user_id', 'users.id')->whereIn('tasks.project_id', $ids), 'menit_kerja')
            ->selectSub(DB::table('project_activity_participants')->join('project_activities', 'project_activities.id', '=', 'project_activity_participants.project_activity_id')
                ->selectRaw('coalesce(sum(project_activities.duration_minutes),0)')->whereColumn('project_activity_participants.user_id', 'users.id')->whereIn('project_activities.project_id', $ids), 'menit_aktivitas')
            ->orderByDesc('aktif')->orderBy('users.name');
        if ($divisionId) {
            $q->whereIn('users.id', DB::table('division_members')->select('user_id')->where('division_id', $divisionId)->where('is_active', 1));
        }

        return $q->get();
    }

    /** Ringkasan per divisi: anggota unik di divisi, project yang melibatkan divisi, task aktif anggota divisi. */
    public function perDivisi(User $user): Collection
    {
        $ids = Scope::projects($user)->select('projects.id');

        return DB::table('divisions')->join('companies', 'companies.id', '=', 'divisions.company_id')->where('divisions.is_active', 1)
            ->select('divisions.id', 'divisions.name', 'companies.name as company')
            ->selectSub(DB::table('division_members')->selectRaw('count(distinct user_id)')->whereColumn('division_id', 'divisions.id')->where('is_active', 1), 'anggota')
            ->selectSub(DB::table('project_divisions')->selectRaw('count(distinct project_id)')->whereColumn('division_id', 'divisions.id')->whereIn('project_id', $ids), 'project')
            ->selectSub(DB::table('task_assignees')->join('tasks', 'tasks.id', '=', 'task_assignees.task_id')
                ->join('division_members', 'division_members.user_id', '=', 'task_assignees.user_id')
                ->selectRaw('count(distinct task_assignees.task_id)')->whereColumn('division_members.division_id', 'divisions.id')->where('division_members.is_active', 1)
                ->whereNull('tasks.deleted_at')->whereNull('tasks.parent_id')->where('tasks.status', '!=', 'done')->whereIn('tasks.project_id', $ids), 'task_aktif')
            ->orderBy('companies.name')->orderBy('divisions.name')->get();
    }

    /** Jejak perubahan jadwal untuk laporan operasional. */
    public function jejakJadwal(User $user, ?string $dari = null, ?string $sampai = null): Collection
    {
        $q = DB::table('schedule_change_requests as s')->join('projects', 'projects.id', '=', 's.project_id')
            ->leftJoin('tasks', 'tasks.id', '=', 's.task_id')->leftJoin('users as r', 'r.id', '=', 's.requested_by')->leftJoin('users as d', 'd.id', '=', 's.decided_by')
            ->leftJoin('project_activities as a', 'a.id', '=', 's.project_activity_id')
            ->whereIn('s.project_id', Scope::projects($user)->select('projects.id'))
            ->select('s.id', 'projects.name as project', 'tasks.title as task', 's.previous_due_at', 's.proposed_due_at', 's.reason', 's.status', 's.requested_at', 's.decided_at',
                'r.name as pengaju', 'd.name as pemutus', 'a.title as aktivitas',
                DB::raw('coalesce(tasks.baseline_due_at, projects.baseline_due_at) as baseline'))
            ->orderByDesc('s.requested_at');
        if ($dari) {
            $q->where('s.requested_at', '>=', $dari);
        }
        if ($sampai) {
            $q->where('s.requested_at', '<=', $sampai);
        }

        return $q->get();
    }

    /** Progress per project/periode (dipakai halaman project). */
    public static function progresProject(int $projectId, ?int $periodeId = null): array
    {
        $q = DB::table('tasks')->where('project_id', $projectId)->whereNull('parent_id')->whereNull('deleted_at');
        if ($periodeId) {
            $q->where('project_period_id', $periodeId);
        }
        $total = (clone $q)->count();
        $selesai = (clone $q)->where('status', 'done')->count();

        return ['total' => $total, 'selesai' => $selesai, 'persen' => $total ? (int) round($selesai * 100 / $total) : 0];
    }
}
