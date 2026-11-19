<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * I13 bulk actions on the task list: start several tasks, change their PIC, or add one assignee. Every task goes through
 * the same policy and status rules as a single change (TaskPolicy, TaskWorkflowService, ProjectTeamService); a task that
 * cannot change is skipped with the reason instead of failing the whole batch.
 */
class TaskBulkService
{
    public const ACTIONS = ['start' => 'Mulai kerjakan', 'pic' => 'Ganti PIC', 'assignee' => 'Tambah pelaksana'];

    public function __construct(private TaskWorkflowService $workflow, private ProjectTeamService $team) {}

    /**
     * What the bulk bar offers this user on a task list: the actions their role allows and the people who can be chosen
     * (active members of the listed projects, with the number of open tasks they hold). Null when no action is allowed.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder|array<int>  $projectIds
     * @return array{actions: array<string, string>, people: list<array{id: int, name: string, open: int}>}|null
     */
    public static function options(User $u, $projectIds): ?array
    {
        $actions = array_filter([
            'start' => $u->hasPermission('tasks.move_status'),
            'pic' => $u->hasPermission('tasks.assign'),
            'assignee' => $u->hasPermission('tasks.assign'),
        ]);
        if ($actions === []) {
            return null;
        }
        $people = DB::table('project_members')->join('users', 'users.id', '=', 'project_members.user_id')->where('project_members.is_active', 1)
            ->whereIn('project_members.project_id', $projectIds)->distinct()->orderBy('users.name')->get(['users.id', 'users.name']);
        $load = \App\Support\ProjectPeople::openTasks($people->pluck('id')->all());

        return [
            'actions' => array_intersect_key(self::ACTIONS, $actions),
            'people' => $people->map(fn ($p) => ['id' => (int) $p->id, 'name' => $p->name, 'open' => (int) ($load[$p->id] ?? 0)])->all(),
        ];
    }

    /** @return array{done: int, skipped: list<array{title: string, reason: string}>} */
    public function apply(User $by, array $ids, string $action, ?int $userId = null): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $tasks = Scope::tasks($by)->whereNull('tasks.parent_id')->whereIn('tasks.id', $ids)
            ->with(['project:id,name,pic_user_id,company_id', 'assignees:id'])->select('tasks.*')->get()->keyBy('id');
        $person = $userId ? User::query()->find($userId) : null;
        $members = $person ? DB::table('project_members')->where('user_id', $person->id)->where('is_active', 1)
            ->whereIn('project_id', $tasks->pluck('project_id')->unique())->pluck('project_id')->map(fn ($n) => (int) $n)->all() : [];
        $done = 0;
        $skipped = [];
        foreach ($ids as $id) {
            $t = $tasks->get($id);
            if (! $t) {
                $skipped[] = ['title' => "Task #{$id}", 'reason' => 'tidak ditemukan atau di luar aksesmu'];

                continue;
            }
            $reason = $this->check($by, $t, $action, $person, in_array((int) $t->project_id, $members, true));
            if ($reason === null) {
                try {
                    $this->change($by, $t, $action, $person);
                    $done++;

                    continue;
                } catch (ValidationException $e) {
                    $reason = collect($e->errors())->flatten()->first() ?? 'tidak bisa diubah';
                }
            }
            $skipped[] = ['title' => $t->title, 'reason' => $reason];
        }

        return ['done' => $done, 'skipped' => $skipped];
    }

    /** Why this task cannot take the change, or null when it can. */
    private function check(User $by, Task $t, string $action, ?User $person, bool $isMember): ?string
    {
        if ($action === 'start') {
            if (! Gate::forUser($by)->allows('moveStatus', $t)) {
                return 'kamu tidak berizin memindah status task ini';
            }

            return $t->status === 'backlog' ? null : 'sudah tidak di Antrian';
        }
        if (! Gate::forUser($by)->allows('assign', $t)) {
            return 'kamu tidak berizin mengatur pelaksana task ini';
        }
        if (! $person) {
            return 'orang yang dipilih tidak ditemukan';
        }
        if (! $isMember) {
            return "{$person->name} bukan anggota project {$t->project?->name} (tambahkan dulu lewat Tim project)";
        }
        if ($action === 'pic' && (int) $t->pic_user_id === (int) $person->id) {
            return "{$person->name} sudah PIC task ini";
        }
        if ($action === 'assignee' && $t->assignees->contains('id', $person->id)) {
            return "{$person->name} sudah pelaksana task ini";
        }

        return null;
    }

    private function change(User $by, Task $t, string $action, ?User $person): void
    {
        $current = $t->assignees->pluck('id')->map(fn ($n) => (int) $n)->all();
        match ($action) {
            'start' => $this->workflow->move($t, 'in_progress', $by),
            // A task that has started keeps the previous PIC as an assignee, so nobody silently loses work in progress;
            // a task still in the queue is handed over completely (the previous PIC was only there because they were PIC).
            'pic' => $this->team->setAssignees($t, (int) $person->id,
                $t->status === 'backlog' ? array_values(array_diff($current, [(int) $t->pic_user_id])) : $current, $by),
            'assignee' => $this->team->setAssignees($t, (int) $t->pic_user_id, [...$current, (int) $person->id], $by),
        };
    }
}
