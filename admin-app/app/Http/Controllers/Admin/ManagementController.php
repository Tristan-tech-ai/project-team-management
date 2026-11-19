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
use App\Support\Codes;
use App\Support\Dates;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Management: Overview, Project Monitoring, Division & Member Monitoring, Operational Report + CSV export. */
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
            'condition' => $monitor->countBy('condition'),
            'review' => TaskSubmission::query()->where('status', 'pending')->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))->with(['task:id,title,project_id', 'submitter:id,name'])->oldest('submitted_at')->limit(5)->get(),
            'operations' => ProjectActivity::query()->whereIn('project_id', Scope::projects($u)->select('projects.id'))->with(['project:id,name'])->latest('id')->limit(5)->get(),
        ]);
    }

    public function projects(Request $request): View
    {
        abort_unless($request->user()->hasPermission('management.view'), 403);
        $filter = $request->only(['project_type', 'status', 'company_id', 'client_id']);

        return view('admin.management.projects', ['rows' => $this->m->monitoring($request->user(), $filter), 'filter' => $filter,
            'companies' => Scope::filterCompanies($request->user()), 'clients' => Scope::filterClients($request->user())]);
    }

    public function teams(Request $request): View
    {
        abort_unless($request->user()->hasPermission('management.view'), 403);
        $div = $request->integer('division_id') ?: null;

        return view('admin.management.teams', ['divisions' => $this->m->perDivision($request->user()), 'people' => $this->m->memberWorkload($request->user(), $div),
            'divisionOptions' => Scope::filterDivisions($request->user()), 'selectedDivision' => $div]);
    }

    public function reports(Request $request): View
    {
        abort_unless($request->user()->hasPermission('management.view'), 403);
        [$from, $until] = $this->range($request);

        return view('admin.management.reports', [
            'from' => $from, 'until' => $until,
            'trail' => $this->m->scheduleTrail($request->user(), $from->copy()->utc(), $until->copy()->utc()),
            'activities' => ProjectActivity::query()->whereIn('project_id', Scope::projects($request->user())->select('projects.id'))
                ->whereBetween('created_at', [$from->copy()->utc(), $until->copy()->utc()])->with(['project:id,name', 'participants:id,name'])->latest('id')->limit(100)->get(),
            'canExport' => $request->user()->hasPermission('management.export'),
        ]);
    }

    /** CSV export (Excel reads UTF-8 BOM). Values starting with = + - @ are neutralized so they never run as formulas. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('management.export'), 403);
        $kind = $request->validate(['kind' => ['required', 'in:monitoring,schedule,members']])['kind'];
        [$from, $until] = $this->range($request);
        $u = $request->user();
        $rows = match ($kind) {
            'monitoring' => array_merge([['Kode', 'Project', 'Tipe', 'Status', 'Company', 'Client', 'PIC', 'Task utama', 'Selesai', 'Progress %', 'Terlambat', 'Anggota unik', 'Divisi', 'Target awal', 'Deadline terbaru', 'Kondisi']],
                $this->m->monitoring($u)->map(fn ($p) => [$p->code, $p->name, Codes::label(Codes::PROJECT_TYPE, $p->project_type), Codes::label(Codes::PROJECT_STATUS, $p->status), $p->company, $p->client ?? '-', $p->pic,
                    $p->main_tasks, $p->done_tasks, $p->progress, $p->overdue_tasks, $p->members, $p->divisions,
                    Dates::date($p->baseline_due_at ? Carbon::parse($p->baseline_due_at) : null), Dates::date($p->due_at ? Carbon::parse($p->due_at) : null),
                    ManagementMetricsService::CONDITIONS[$p->condition] ?? $p->condition])->all()),
            'schedule' => array_merge([['Project', 'Task', 'Target awal', 'Deadline sebelum', 'Deadline usulan', 'Alasan', 'Aktivitas terkait', 'Status', 'Pengaju', 'Diajukan', 'Pemutus', 'Diputuskan']],
                $this->m->scheduleTrail($u, $from->copy()->utc(), $until->copy()->utc())->map(fn ($s) => [$s->project, $s->task ?? '(project)', $this->dateValue($s->baseline), $this->dateValue($s->previous_due_at),
                    $this->dateValue($s->proposed_due_at), $s->reason, $s->activities ?? '-', Codes::label(Codes::SCHEDULE_STATUS, $s->status), $s->requester, $this->dateValue($s->requested_at), $s->decider ?? '-', $this->dateValue($s->decided_at)])->all()),
            'members' => array_merge([['Anggota', 'Project', 'Task aktif', 'Terlambat', 'Selesai', 'Selesai tepat waktu', 'Hari rutin dicentang', 'Hari rutin lewat', 'Menit kerja', 'Menit aktivitas']],
                $this->m->memberWorkload($u)->map(fn ($o) => [$o->name, $o->project, $o->active, $o->overdue, $o->done, $o->on_time, $o->routine_done, $o->routine_due, $o->work_minutes, $o->activity_minutes])->all()),
        };
        $name = 'laporan-'.$kind.'-'.now()->setTimezone(Dates::zone())->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            foreach ($rows as $b) {
                fputcsv($f, array_map(fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v, $b), ';');
            }
            fclose($f);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function dateValue($v): string
    {
        return $v ? Dates::dateTime(Carbon::parse($v)) : '-';
    }

    private function range(Request $request): array
    {
        $zone = Dates::zone();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'), $zone)->startOfDay() : now($zone)->startOfMonth();
        $until = $request->filled('until') ? Carbon::parse($request->input('until'), $zone)->endOfDay() : now($zone)->endOfDay();

        return [$from, $until];
    }
}
