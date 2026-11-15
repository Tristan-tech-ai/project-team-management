<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Http\Requests\Work\ActivityRequest;
use App\Http\Requests\Work\DecisionRequest;
use App\Http\Requests\Work\ScheduleChangeFormRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ScheduleChangeRequest;
use App\Models\Task;
use App\Services\ScheduleChangeService;
use App\Services\Scope;
use App\Support\Codes;
use App\Support\Dates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/** Activities, schedule changes, and calendar events: identical in both apps (Technical Guide 8.4, 8.6, 9.2). */
class OperationsController extends Controller
{
    public function __construct(private ScheduleChangeService $schedule) {}

    /** POST /projects/{id}/activities. Optionally proposes a deadline shift linked to this activity right away. */
    public function activities(ActivityRequest $request, Project $project): RedirectResponse
    {
        $d = $request->validated();
        $a = DB::transaction(function () use ($d, $project, $request) {
            $a = ProjectActivity::create([
                'project_id' => $project->id, 'task_id' => $d['task_id'] ?? null, 'created_by' => $request->user()->id,
                'activity_type' => $d['activity_type'], 'title' => $d['title'], 'description' => $d['description'] ?? null, 'status' => $d['status'],
                'scheduled_at' => Dates::fromInput($d['scheduled_at'] ?? null), 'occurred_at' => Dates::fromInput($d['occurred_at'] ?? null),
                'duration_minutes' => $d['duration_minutes'] ?? null, 'impact_type' => $d['impact_type'], 'impact_notes' => $d['impact_notes'] ?? null,
                'resolution_notes' => $d['resolution_notes'] ?? null,
            ]);
            $now = now();
            foreach (array_unique(array_map('intval', $d['participants'] ?? [])) as $uid) {
                DB::table('project_activity_participants')->insert(['project_activity_id' => $a->id, 'user_id' => $uid, 'created_at' => $now, 'updated_at' => $now]);
            }
            if (! empty($d['propose_deadline'])) {
                $task = ! empty($d['task_id']) ? Task::query()->find($d['task_id']) : null;
                $this->schedule->propose($project, $task, $a, Dates::fromInput($d['propose_deadline']), $d['deadline_reason'], $request->user());
            }

            return $a;
        });

        $message = Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type).' tercatat'.(! empty($d['propose_deadline']) ? ' dan pengajuan deadline menunggu persetujuan.' : '.');
        // From the Team App form the person lands on their activity list with the new entry on top, not on an empty form again.
        if ($request->input('after') === 'list' && Route::has('activities.index')) {
            return redirect()->route('activities.index')->with('ok', $message);
        }

        return back()->with('ok', $message);
    }

    public function proposeSchedule(ScheduleChangeFormRequest $request): RedirectResponse
    {
        $project = Project::query()->findOrFail($request->integer('project_id'));
        $task = $request->filled('task_id') ? Task::query()->findOrFail($request->integer('task_id')) : null;
        if (! (Scope::isManager($request->user(), $project) || Scope::isProjectMember($request->user(), $project))) {
            abort(403);
        }
        if ($task && $task->parent_id !== null) {
            throw ValidationException::withMessages(['task_id' => 'Deadline diajukan untuk task utama, bukan checklist.']);
        }
        $activity = $request->filled('project_activity_id') ? ProjectActivity::query()->findOrFail($request->integer('project_activity_id')) : null;
        $this->schedule->propose($project, $task, $activity, Dates::fromInput($request->input('proposed_due_at')), $request->input('reason'), $request->user());

        return back()->with('ok', 'Pengajuan deadline terkirim. Target awal tetap tercatat.');
    }

    public function decideSchedule(DecisionRequest $request, ScheduleChangeRequest $scheduleChange): RedirectResponse
    {
        $r = $this->schedule->decide($scheduleChange, $request->input('decision') === 'approved', $request->input('decision_note'), $request->user());

        return back()->with('ok', $r->status === 'approved' ? 'Deadline baru disetujui dan diterapkan.' : 'Pengajuan ditolak.');
    }

    /**
     * GET /calendar/events?mode=schedule|done|publication|activities&start=&end=&project_id=
     * Schedule: task due_at + activity scheduled_at; Done: tasks.completed_at; Publication: scheduled_publish_at/published_at.
     * Never uses updated_at as a completion date (Technical Guide 8.6).
     */
    public function events(Request $request): JsonResponse
    {
        $data = $request->validate(['mode' => ['required', 'in:schedule,done,publication,activities'], 'start' => ['required', 'date'], 'end' => ['required', 'date'],
            'project_id' => ['nullable', 'integer'], 'mine' => ['nullable', 'boolean']]);
        $user = $request->user();
        abort_unless($user->hasPermission('calendar.view'), 403);
        $start = \Illuminate\Support\Carbon::parse($data['start'])->utc();
        $end = \Illuminate\Support\Carbon::parse($data['end'])->utc();
        // My Calendar (Team App, Technical Guide 5.2): activities from joined projects; the Admin calendar follows the company scope.
        $projectQuery = ! empty($data['mine']) ? Scope::memberProjects($user) : Scope::projects($user);
        $tasks = Scope::tasks($user)->whereNull('tasks.parent_id')->with('project:id,name');
        if (! empty($data['project_id'])) {
            $tasks->where('tasks.project_id', $data['project_id']);
        }
        if (! empty($data['mine'])) {
            $tasks->where(fn ($w) => $w->where('tasks.pic_user_id', $user->id)->orWhereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $user->id)));
        }
        $ev = [];
        $url = fn (Task $t) => route('tasks.show', $t);
        if ($data['mode'] === 'schedule') {
            foreach ((clone $tasks)->whereBetween('tasks.due_at', [$start, $end])->get() as $t) {
                $late = $t->status !== 'done' && $t->due_at->isPast();
                $ev[] = ['id' => 't'.$t->id, 'title' => 'Deadline: '.$t->title, 'start' => Dates::local($t->due_at)->toIso8601String(), 'url' => $url($t),
                    'classNames' => [$late ? 'ev-late' : ($t->status === 'done' ? 'ev-done' : 'ev-deadline')],
                    'extendedProps' => ['project' => $t->project?->name, 'status' => ($late ? 'Terlambat · ' : '').Codes::label(Codes::TASK_STATUS, $t->status)]];
            }
            $activityQuery = ProjectActivity::query()->whereIn('project_id', $projectQuery->select('projects.id'))->whereBetween('scheduled_at', [$start, $end])->with('project:id,name');
            if (! empty($data['project_id'])) {
                $activityQuery->where('project_id', $data['project_id']);
            }
            foreach ($activityQuery->get() as $a) {
                $ev[] = ['id' => 'a'.$a->id, 'title' => Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type).': '.$a->title, 'start' => Dates::local($a->scheduled_at)->toIso8601String(),
                    'classNames' => ['ev-activity'], 'extendedProps' => ['project' => $a->project?->name, 'status' => Codes::label(Codes::ACTIVITY_STATUS, $a->status)]];
            }
        } elseif ($data['mode'] === 'done') {
            foreach ((clone $tasks)->whereNotNull('tasks.completed_at')->whereBetween('tasks.completed_at', [$start, $end])->get() as $t) {
                $ev[] = ['id' => 'c'.$t->id, 'title' => 'Selesai: '.$t->title, 'start' => Dates::local($t->completed_at)->toIso8601String(), 'url' => $url($t),
                    'classNames' => ['ev-done'], 'extendedProps' => ['project' => $t->project?->name, 'status' => 'Selesai aktual']];
            }
        } elseif ($data['mode'] === 'publication') {
            foreach ((clone $tasks)->where(fn ($w) => $w->whereBetween('tasks.scheduled_publish_at', [$start, $end])->orWhereBetween('tasks.published_at', [$start, $end]))->get() as $t) {
                $when = $t->published_at ?? $t->scheduled_publish_at;
                $ev[] = ['id' => 'p'.$t->id, 'title' => ($t->published_at ? 'Terbit: ' : 'Jadwal terbit: ').$t->title.($t->platform ? ' ('.$t->platform.')' : ''),
                    'start' => Dates::local($when)->toIso8601String(), 'url' => $url($t), 'classNames' => [$t->published_at ? 'ev-done' : 'ev-publication'],
                    'extendedProps' => ['project' => $t->project?->name, 'status' => Codes::label(Codes::PUBLICATION_STATUS, $t->publication_status)]];
            }
        } else {
            $activityQuery = ProjectActivity::query()->whereIn('project_id', $projectQuery->select('projects.id'))->with('project:id,name')
                ->where(fn ($w) => $w->whereBetween('occurred_at', [$start, $end])->orWhere(fn ($x) => $x->whereNull('occurred_at')->whereBetween('scheduled_at', [$start, $end])));
            if (! empty($data['project_id'])) {
                $activityQuery->where('project_id', $data['project_id']);
            }
            foreach ($activityQuery->get() as $a) {
                $ev[] = ['id' => 'k'.$a->id, 'title' => Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type).': '.$a->title, 'start' => Dates::local($a->occurred_at ?? $a->scheduled_at)->toIso8601String(),
                    'classNames' => [$a->impact_type === 'blocked' ? 'ev-late' : 'ev-activity'],
                    'extendedProps' => ['project' => $a->project?->name, 'status' => Codes::label(Codes::IMPACT_TYPE, $a->impact_type).($a->duration_minutes ? ' · '.$a->duration_minutes.' menit' : '')]];
            }
        }

        return response()->json($ev);
    }
}
