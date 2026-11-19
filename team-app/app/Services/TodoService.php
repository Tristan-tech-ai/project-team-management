<?php

namespace App\Services;

use App\Models\ScheduleChangeRequest;
use App\Models\Task;
use App\Models\TaskSubmission;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * I1 "Perlu kamu kerjakan": what this person should act on now, for every role, built from data that already exists.
 * Order: results waiting for my review, deadline requests waiting for my decision, my tasks returned for revision, my late tasks,
 * my tasks due within two days. Each entry links straight to the place where the action happens. Same scope rules as the lists.
 */
class TodoService
{
    public function __construct(private ScheduleChangeService $schedule) {}

    /** @return list<array{kind:string, tone:string, title:string, detail:string, when:?string, url:string, panel:bool}> panel: the url is a task, opened in the side panel (I3) */
    public function for(User $u, int $limit = 8): array
    {
        $items = [];
        $taskUrl = fn (int $id) => route('tasks.show', $id);
        $mine = fn ($q) => $q->where(fn ($w) => $w->where('tasks.pic_user_id', $u->id)
            ->orWhereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $u->id)));

        if ($u->hasPermission('tasks.review')) {
            $pending = TaskSubmission::query()->where('status', 'pending')->whereIn('task_id', Scope::tasks($u)->select('tasks.id'))
                ->with(['task:id,title,project_id,pic_user_id', 'task.project:id,name,pic_user_id,company_id', 'submitter:id,name'])->oldest('submitted_at')->limit($limit)->get();
            foreach ($pending as $s) {
                if ($s->task && $u->can('review', $s->task)) {
                    $items[] = ['kind' => 'review', 'tone' => 'review', 'title' => "Review hasil {$s->task->title}",
                        'detail' => "{$s->submitter?->name} · {$s->task->project?->name}", 'when' => Dates::relative($s->submitted_at, past: true), 'url' => $taskUrl($s->task_id).'#review', 'panel' => true];
                }
            }
        }

        $requests = ScheduleChangeRequest::query()->where('status', 'pending')->whereIn('project_id', Scope::projects($u)->select('projects.id'))
            ->with(['task:id,title,project_id,pic_user_id', 'task.project:id,name,pic_user_id,company_id', 'project:id,name,pic_user_id,company_id', 'requester:id,name'])->oldest('requested_at')->limit($limit)->get();
        foreach ($requests as $r) {
            if ((int) $r->requested_by !== (int) $u->id && $this->schedule->canDecide($u, $r)) {
                $items[] = ['kind' => 'schedule', 'tone' => 'review', 'title' => 'Putuskan geser deadline '.($r->task?->title ?? $r->project?->name),
                    'detail' => "{$r->requester?->name} minta ".Dates::date($r->proposed_due_at), 'when' => Dates::relative($r->requested_at, past: true),
                    'url' => $r->task_id ? $taskUrl($r->task_id).'#schedule' : (Route::has('activities.impacts') ? route('activities.impacts') : route('projects.show', $r->project_id)),
                    'panel' => (bool) $r->task_id];
            }
        }

        $returned = $mine(Scope::tasks($u))->whereNull('tasks.parent_id')->where('tasks.status', 'in_progress')
            ->whereExists(fn ($q) => $q->from('task_submissions as s')->whereColumn('s.task_id', 'tasks.id')->where('s.status', 'revision')
                ->whereRaw('s.id = (select max(s2.id) from task_submissions s2 where s2.task_id = tasks.id)'))
            ->with('project:id,name')->orderBy('tasks.due_at')->limit($limit)->get(['tasks.id', 'tasks.title', 'tasks.project_id', 'tasks.due_at']);
        foreach ($returned as $t) {
            $items[] = ['kind' => 'revision', 'tone' => 'late', 'title' => "Perbaiki {$t->title}", 'detail' => "Dikembalikan untuk revisi · {$t->project?->name}",
                'when' => $t->due_at ? Dates::relative($t->due_at) : null, 'url' => $taskUrl($t->id), 'panel' => true];
        }

        // Routine tasks: today's checklist item of a daily task I hold, until it is ticked.
        $dayStart = Dates::local(now())->startOfDay()->utc();
        $routine = Task::query()->whereNotNull('tasks.parent_id')->where('tasks.status', '!=', 'done')
            ->whereBetween('tasks.due_at', [$dayStart, $dayStart->copy()->addDay()->subSecond()])
            ->whereIn('tasks.parent_id', $mine(Scope::tasks($u))->whereNull('tasks.parent_id')->whereIn('tasks.status', ['backlog', 'in_progress'])->select('tasks.id'))
            ->with(['parent:id,title,project_id', 'parent.project:id,name'])->orderBy('tasks.due_at')->limit($limit)->get(['tasks.id', 'tasks.parent_id', 'tasks.title', 'tasks.due_at']);
        foreach ($routine as $s) {
            $items[] = ['kind' => 'routine', 'tone' => $s->due_at->isPast() ? 'late' : 'info', 'title' => 'Rutin hari ini: '.$s->parent?->title,
                'detail' => $s->title.' · '.($s->parent?->project?->name ?? ''), 'when' => Dates::relative($s->due_at), 'url' => $taskUrl($s->parent_id).'#checklist', 'panel' => true];
        }

        $open = $mine(Scope::tasks($u))->whereNull('tasks.parent_id')->whereIn('tasks.status', ['backlog', 'in_progress'])->whereNotNull('tasks.due_at')
            ->where('tasks.due_at', '<', now()->addDays(2))->whereNotIn('tasks.id', $returned->pluck('id'))
            ->with('project:id,name')->orderBy('tasks.due_at')->limit($limit)->get(['tasks.id', 'tasks.title', 'tasks.project_id', 'tasks.due_at', 'tasks.status']);
        foreach ($open as $t) {
            $late = $t->due_at->isPast();
            $items[] = ['kind' => $late ? 'late' : 'due', 'tone' => $late ? 'late' : 'info',
                'title' => ($t->status === 'backlog' ? 'Mulai ' : 'Selesaikan ').$t->title, 'detail' => $t->project?->name ?? '', 'when' => Dates::relative($t->due_at), 'url' => $taskUrl($t->id), 'panel' => true];
        }

        return array_slice($items, 0, $limit);
    }
}
