<?php

namespace App\Http\Controllers\Bersama;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kerja\ActivityRequest;
use App\Http\Requests\Kerja\DecisionRequest;
use App\Http\Requests\Kerja\ScheduleChangeFormRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ScheduleChangeRequest;
use App\Models\Task;
use App\Services\ScheduleChangeService;
use App\Services\Scope;
use App\Support\Kode;
use App\Support\Waktu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Aktivitas, perubahan jadwal, dan event kalender: identik di kedua aplikasi (Petunjuk Teknis 8.4, 8.6, 9.2). */
class OperasionalController extends Controller
{
    public function __construct(private ScheduleChangeService $jadwal) {}

    /** POST /projects/{id}/activities. Opsional langsung mengajukan geser deadline yang ditautkan ke aktivitas ini. */
    public function aktivitas(ActivityRequest $request, Project $project): RedirectResponse
    {
        $d = $request->validated();
        $a = DB::transaction(function () use ($d, $project, $request) {
            $a = ProjectActivity::create([
                'project_id' => $project->id, 'task_id' => $d['task_id'] ?? null, 'created_by' => $request->user()->id,
                'activity_type' => $d['activity_type'], 'title' => $d['title'], 'description' => $d['description'] ?? null, 'status' => $d['status'],
                'scheduled_at' => Waktu::dariInput($d['scheduled_at'] ?? null), 'occurred_at' => Waktu::dariInput($d['occurred_at'] ?? null),
                'duration_minutes' => $d['duration_minutes'] ?? null, 'impact_type' => $d['impact_type'], 'impact_notes' => $d['impact_notes'] ?? null,
                'resolution_notes' => $d['resolution_notes'] ?? null,
            ]);
            $sekarang = now();
            foreach (array_unique(array_map('intval', $d['participants'] ?? [])) as $uid) {
                DB::table('project_activity_participants')->insert(['project_activity_id' => $a->id, 'user_id' => $uid, 'created_at' => $sekarang, 'updated_at' => $sekarang]);
            }
            if (! empty($d['ajukan_deadline'])) {
                $task = ! empty($d['task_id']) ? Task::query()->find($d['task_id']) : null;
                $this->jadwal->ajukan($project, $task, $a, Waktu::dariInput($d['ajukan_deadline']), $d['alasan_deadline'], $request->user());
            }

            return $a;
        });

        return back()->with('ok', Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type).' tercatat'.(! empty($d['ajukan_deadline']) ? ' dan pengajuan deadline menunggu persetujuan.' : '.'));
    }

    public function ajukanJadwal(ScheduleChangeFormRequest $request): RedirectResponse
    {
        $project = Project::query()->findOrFail($request->integer('project_id'));
        $task = $request->filled('task_id') ? Task::query()->findOrFail($request->integer('task_id')) : null;
        if (! (Scope::pengelola($request->user(), $project) || Scope::anggota($request->user(), $project))) {
            abort(403);
        }
        if ($task && $task->parent_id !== null) {
            throw ValidationException::withMessages(['task_id' => 'Deadline diajukan untuk task utama, bukan checklist.']);
        }
        $activity = $request->filled('project_activity_id') ? ProjectActivity::query()->findOrFail($request->integer('project_activity_id')) : null;
        $this->jadwal->ajukan($project, $task, $activity, Waktu::dariInput($request->input('proposed_due_at')), $request->input('reason'), $request->user());

        return back()->with('ok', 'Pengajuan deadline terkirim. Target awal tetap tercatat.');
    }

    public function putuskanJadwal(DecisionRequest $request, ScheduleChangeRequest $scheduleChange): RedirectResponse
    {
        $r = $this->jadwal->putuskan($scheduleChange, $request->input('keputusan') === 'approved', $request->input('decision_note'), $request->user());

        return back()->with('ok', $r->status === 'approved' ? 'Deadline baru disetujui dan diterapkan.' : 'Pengajuan ditolak.');
    }

    /**
     * GET /calendar/events?mode=jadwal|selesai|publikasi|aktivitas&start=&end=&project_id=
     * Jadwal: due_at task + scheduled_at aktivitas; Selesai: tasks.completed_at; Publikasi: scheduled_publish_at/published_at.
     * Tidak pernah memakai updated_at sebagai tanggal selesai (Petunjuk Teknis 8.6).
     */
    public function events(Request $request): JsonResponse
    {
        $data = $request->validate(['mode' => ['required', 'in:jadwal,selesai,publikasi,aktivitas'], 'start' => ['required', 'date'], 'end' => ['required', 'date'],
            'project_id' => ['nullable', 'integer'], 'saya' => ['nullable', 'boolean']]);
        $user = $request->user();
        abort_unless($user->hasPermission('calendar.view'), 403);
        $mulai = \Illuminate\Support\Carbon::parse($data['start'])->utc();
        $akhir = \Illuminate\Support\Carbon::parse($data['end'])->utc();
        $tasks = Scope::tasks($user)->whereNull('tasks.parent_id')->with('project:id,name');
        if (! empty($data['project_id'])) {
            $tasks->where('tasks.project_id', $data['project_id']);
        }
        if (! empty($data['saya'])) {
            $tasks->where(fn ($w) => $w->where('tasks.pic_user_id', $user->id)->orWhereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $user->id)));
        }
        $ev = [];
        $url = fn (Task $t) => route('tasks.show', $t);
        if ($data['mode'] === 'jadwal') {
            foreach ((clone $tasks)->whereBetween('tasks.due_at', [$mulai, $akhir])->get() as $t) {
                $telat = $t->status !== 'done' && $t->due_at->isPast();
                $ev[] = ['id' => 't'.$t->id, 'title' => 'Deadline: '.$t->title, 'start' => Waktu::lokal($t->due_at)->toIso8601String(), 'url' => $url($t),
                    'classNames' => [$telat ? 'ev-telat' : ($t->status === 'done' ? 'ev-selesai' : 'ev-deadline')],
                    'extendedProps' => ['project' => $t->project?->name, 'status' => ($telat ? 'Terlambat · ' : '').Kode::label(Kode::TASK_STATUS, $t->status)]];
            }
            $akt = ProjectActivity::query()->whereIn('project_id', Scope::projects($user)->select('projects.id'))->whereBetween('scheduled_at', [$mulai, $akhir])->with('project:id,name');
            if (! empty($data['project_id'])) {
                $akt->where('project_id', $data['project_id']);
            }
            foreach ($akt->get() as $a) {
                $ev[] = ['id' => 'a'.$a->id, 'title' => Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type).': '.$a->title, 'start' => Waktu::lokal($a->scheduled_at)->toIso8601String(),
                    'classNames' => ['ev-aktivitas'], 'extendedProps' => ['project' => $a->project?->name, 'status' => Kode::label(Kode::ACTIVITY_STATUS, $a->status)]];
            }
        } elseif ($data['mode'] === 'selesai') {
            foreach ((clone $tasks)->whereNotNull('tasks.completed_at')->whereBetween('tasks.completed_at', [$mulai, $akhir])->get() as $t) {
                $ev[] = ['id' => 'c'.$t->id, 'title' => 'Selesai: '.$t->title, 'start' => Waktu::lokal($t->completed_at)->toIso8601String(), 'url' => $url($t),
                    'classNames' => ['ev-selesai'], 'extendedProps' => ['project' => $t->project?->name, 'status' => 'Selesai aktual']];
            }
        } elseif ($data['mode'] === 'publikasi') {
            foreach ((clone $tasks)->where(fn ($w) => $w->whereBetween('tasks.scheduled_publish_at', [$mulai, $akhir])->orWhereBetween('tasks.published_at', [$mulai, $akhir]))->get() as $t) {
                $kapan = $t->published_at ?? $t->scheduled_publish_at;
                $ev[] = ['id' => 'p'.$t->id, 'title' => ($t->published_at ? 'Terbit: ' : 'Jadwal terbit: ').$t->title.($t->platform ? ' ('.$t->platform.')' : ''),
                    'start' => Waktu::lokal($kapan)->toIso8601String(), 'url' => $url($t), 'classNames' => [$t->published_at ? 'ev-selesai' : 'ev-publikasi'],
                    'extendedProps' => ['project' => $t->project?->name, 'status' => Kode::label(Kode::PUBLICATION_STATUS, $t->publication_status)]];
            }
        } else {
            $akt = ProjectActivity::query()->whereIn('project_id', Scope::projects($user)->select('projects.id'))->with('project:id,name')
                ->where(fn ($w) => $w->whereBetween('occurred_at', [$mulai, $akhir])->orWhere(fn ($x) => $x->whereNull('occurred_at')->whereBetween('scheduled_at', [$mulai, $akhir])));
            if (! empty($data['project_id'])) {
                $akt->where('project_id', $data['project_id']);
            }
            foreach ($akt->get() as $a) {
                $ev[] = ['id' => 'k'.$a->id, 'title' => Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type).': '.$a->title, 'start' => Waktu::lokal($a->occurred_at ?? $a->scheduled_at)->toIso8601String(),
                    'classNames' => [$a->impact_type === 'blocked' ? 'ev-telat' : 'ev-aktivitas'],
                    'extendedProps' => ['project' => $a->project?->name, 'status' => Kode::label(Kode::IMPACT_TYPE, $a->impact_type).($a->duration_minutes ? ' · '.$a->duration_minutes.' menit' : '')]];
            }
        }

        return response()->json($ev);
    }
}
