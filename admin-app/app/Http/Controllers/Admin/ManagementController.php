<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\Division;
use App\Models\ProjectActivity;
use App\Models\TaskSubmission;
use App\Services\ManagementMetricsService;
use App\Services\Scope;
use App\Support\Kode;
use App\Support\Waktu;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Management: Overview, Monitoring Project, Monitoring Divisi & Anggota, Laporan Operasional + export CSV. */
class ManagementController extends Controller
{
    public function __construct(private ManagementMetricsService $m) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('management.view'), 403);
        $u = $request->user();
        $monitor = $this->m->monitoring($u);

        return view('admin.management.index', [
            'kpi' => $this->m->kpi($u), 'monitor' => $monitor->whereNotIn('status', ['completed', 'cancelled'])->take(8),
            'kondisi' => $monitor->countBy('kondisi'),
            'review' => TaskSubmission::query()->where('status', 'pending')->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))->with(['task:id,title,project_id', 'submitter:id,name'])->oldest('submitted_at')->limit(5)->get(),
            'operasional' => ProjectActivity::query()->whereIn('project_id', Scope::projects($u)->select('projects.id'))->with(['project:id,name'])->latest('id')->limit(5)->get(),
        ]);
    }

    public function projects(Request $request): View
    {
        abort_unless($request->user()->hasPermission('management.view'), 403);
        $filter = $request->only(['project_type', 'status', 'company_id', 'client_id']);

        return view('admin.management.projects', ['baris' => $this->m->monitoring($request->user(), $filter), 'filter' => $filter,
            'companies' => Company::query()->orderBy('name')->pluck('name', 'id'), 'clients' => Client::query()->orderBy('name')->pluck('name', 'id')]);
    }

    public function teams(Request $request): View
    {
        abort_unless($request->user()->hasPermission('management.view'), 403);
        $div = $request->integer('division_id') ?: null;

        return view('admin.management.teams', ['divisi' => $this->m->perDivisi($request->user()), 'orang' => $this->m->bebanAnggota($request->user(), $div),
            'pilihanDivisi' => Division::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'), 'divDipilih' => $div]);
    }

    public function reports(Request $request): View
    {
        abort_unless($request->user()->hasPermission('management.view'), 403);
        [$dari, $sampai] = $this->rentang($request);

        return view('admin.management.reports', [
            'dari' => $dari, 'sampai' => $sampai,
            'jejak' => $this->m->jejakJadwal($request->user(), $dari->copy()->utc(), $sampai->copy()->utc()),
            'aktivitas' => ProjectActivity::query()->whereIn('project_id', Scope::projects($request->user())->select('projects.id'))
                ->whereBetween('created_at', [$dari->copy()->utc(), $sampai->copy()->utc()])->with(['project:id,name', 'participants:id,name'])->latest('id')->limit(100)->get(),
            'bolehExport' => $request->user()->hasPermission('management.export'),
        ]);
    }

    /** Export CSV (Excel membaca UTF-8 BOM). Nilai diawali = + - @ dinetralkan agar tidak dieksekusi sebagai rumus. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('management.export'), 403);
        $jenis = $request->validate(['jenis' => ['required', 'in:monitoring,jadwal,anggota']])['jenis'];
        [$dari, $sampai] = $this->rentang($request);
        $u = $request->user();
        $baris = match ($jenis) {
            'monitoring' => array_merge([['Kode', 'Project', 'Tipe', 'Status', 'Company', 'Client', 'PIC', 'Task utama', 'Selesai', 'Progress %', 'Terlambat', 'Anggota unik', 'Divisi', 'Target awal', 'Deadline terbaru', 'Kondisi']],
                $this->m->monitoring($u)->map(fn ($p) => [$p->code, $p->name, Kode::label(Kode::PROJECT_TYPE, $p->project_type), Kode::label(Kode::PROJECT_STATUS, $p->status), $p->company, $p->client ?? '-', $p->pic,
                    $p->task_utama, $p->task_selesai, $p->progres, $p->task_terlambat, $p->anggota, $p->divisi,
                    Waktu::tanggal($p->baseline_due_at ? Carbon::parse($p->baseline_due_at) : null), Waktu::tanggal($p->due_at ? Carbon::parse($p->due_at) : null),
                    ManagementMetricsService::KONDISI[$p->kondisi] ?? $p->kondisi])->all()),
            'jadwal' => array_merge([['Project', 'Task', 'Target awal', 'Deadline sebelum', 'Deadline usulan', 'Alasan', 'Aktivitas terkait', 'Status', 'Pengaju', 'Diajukan', 'Pemutus', 'Diputuskan']],
                $this->m->jejakJadwal($u, $dari->copy()->utc(), $sampai->copy()->utc())->map(fn ($s) => [$s->project, $s->task ?? '(project)', $this->tgl($s->baseline), $this->tgl($s->previous_due_at),
                    $this->tgl($s->proposed_due_at), $s->reason, $s->aktivitas ?? '-', Kode::label(Kode::SCHEDULE_STATUS, $s->status), $s->pengaju, $this->tgl($s->requested_at), $s->pemutus ?? '-', $this->tgl($s->decided_at)])->all()),
            'anggota' => array_merge([['Anggota', 'Project', 'Task aktif', 'Terlambat', 'Selesai', 'Menit kerja', 'Menit aktivitas']],
                $this->m->bebanAnggota($u)->map(fn ($o) => [$o->name, $o->project, $o->aktif, $o->terlambat, $o->selesai, $o->menit_kerja, $o->menit_aktivitas])->all()),
        };
        $nama = 'laporan-'.$jenis.'-'.now()->setTimezone(Waktu::zona())->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($baris) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            foreach ($baris as $b) {
                fputcsv($f, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v, $b), ';');
            }
            fclose($f);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function tgl($v): string
    {
        return $v ? Waktu::tanggalJam(Carbon::parse($v)) : '-';
    }

    private function rentang(Request $request): array
    {
        $zona = Waktu::zona();
        $dari = $request->filled('dari') ? Carbon::parse($request->input('dari'), $zona)->startOfDay() : now($zona)->startOfMonth();
        $sampai = $request->filled('sampai') ? Carbon::parse($request->input('sampai'), $zona)->endOfDay() : now($zona)->endOfDay();

        return [$dari, $sampai];
    }
}
