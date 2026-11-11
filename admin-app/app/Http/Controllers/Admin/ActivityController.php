<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ScheduleChangeRequest;
use App\Services\ScheduleChangeService;
use App\Services\Scope;
use App\Support\Kode;
use App\Support\Waktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/** Activities: Daftar Aktivitas (Yajra), Rapat & Kunjungan, Hambatan & Perubahan (approval reschedule). */
class ActivityController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('activities.view'), 403);

        return view('admin.activities.index', ['judul' => 'Daftar Aktivitas', 'ket' => 'Semua rapat, kunjungan, revisi, hambatan, dan kegiatan lain dari project yang bisa kamu lihat.',
            'feed' => route('activities.data'), 'jenis' => null, 'projects' => Scope::projects($request->user())->orderBy('name')->pluck('name', 'id')]);
    }

    public function meetings(Request $request): View
    {
        abort_unless($request->user()->hasPermission('activities.view'), 403);

        return view('admin.activities.index', ['judul' => 'Rapat & Kunjungan', 'ket' => 'Peserta yang benar-benar hadir, tanggal, dan durasi aktual.',
            'feed' => route('activities.data', ['jenis' => 'meeting,visit']), 'jenis' => 'meeting,visit', 'projects' => Scope::projects($request->user())->orderBy('name')->pluck('name', 'id')]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('activities.view'), 403);
        $q = ProjectActivity::query()->whereIn('project_activities.project_id', Scope::projects($request->user())->select('projects.id'))
            ->with(['project:id,name', 'creator:id,name', 'participants:id,name', 'task:id,title'])->select('project_activities.*');
        if ($request->filled('jenis')) {
            $q->whereIn('project_activities.activity_type', explode(',', (string) $request->input('jenis')));
        }
        if ($request->filled('project_id')) {
            $q->where('project_activities.project_id', $request->integer('project_id'));
        }
        if ($request->filled('impact_type')) {
            $q->where('project_activities.impact_type', $request->input('impact_type'));
        }

        return DataTables::eloquent($q)
            ->addColumn('tanggal', fn (ProjectActivity $a) => Waktu::tanggalJam($a->occurred_at ?? $a->scheduled_at))
            ->addColumn('jenis', fn (ProjectActivity $a) => Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type))
            ->addColumn('project_name', fn (ProjectActivity $a) => $a->project?->name)
            ->addColumn('task_title', fn (ProjectActivity $a) => $a->task?->title ?? '-')
            ->addColumn('peserta', fn (ProjectActivity $a) => $a->participants->pluck('name')->implode(', ') ?: '-')
            ->addColumn('durasi', fn (ProjectActivity $a) => $a->duration_minutes !== null ? $a->duration_minutes.' menit' : '-')
            ->addColumn('dampak', fn (ProjectActivity $a) => Kode::label(Kode::IMPACT_TYPE, $a->impact_type))
            ->addColumn('dampak_kelas', fn (ProjectActivity $a) => ['none' => 'netral', 'minor' => 'info', 'delay' => 'risk', 'blocked' => 'blocked'][$a->impact_type] ?? 'netral')
            ->addColumn('status_label', fn (ProjectActivity $a) => Kode::label(Kode::ACTIVITY_STATUS, $a->status))
            ->addColumn('status_label_kelas', fn (ProjectActivity $a) => ['planned' => 'planned', 'done' => 'done', 'cancelled' => 'cancelled'][$a->status] ?? 'netral')
            ->addColumn('aksi', fn (ProjectActivity $a) => [['label' => 'Project', 'url' => route('projects.activities', $a->project_id)]])
            ->orderColumn('tanggal', 'coalesce(project_activities.occurred_at, project_activities.scheduled_at) $1')
            ->toJson();
    }

    /** Hambatan & Perubahan: aktivitas berdampak + antrian persetujuan jadwal (baseline tetap terlihat). */
    public function impacts(Request $request, ScheduleChangeService $svc): View
    {
        abort_unless($request->user()->hasPermission('activities.view'), 403);
        $ids = Scope::projects($request->user())->select('projects.id');

        return view('admin.activities.impacts', [
            'berdampak' => ProjectActivity::query()->whereIn('project_id', $ids)->whereIn('impact_type', ['minor', 'delay', 'blocked'])
                ->with(['project:id,name', 'task:id,title', 'creator:id,name'])->orderByRaw("field(status,'planned','done','cancelled')")->latest('id')->limit(50)->get(),
            'menunggu' => ScheduleChangeRequest::query()->whereIn('project_id', $ids)->where('status', 'pending')
                ->with(['project:id,name,pic_user_id,company_id,baseline_due_at,due_at', 'task:id,title,baseline_due_at,due_at,pic_user_id,project_id', 'requester:id,name', 'activity:id,title'])->oldest('requested_at')->get(),
            'riwayat' => ScheduleChangeRequest::query()->whereIn('project_id', $ids)->where('status', '!=', 'pending')
                ->with(['project:id,name,baseline_due_at', 'task:id,title,baseline_due_at', 'requester:id,name', 'decider:id,name'])->latest('decided_at')->limit(30)->get(),
            'svc' => $svc,
        ]);
    }
}
