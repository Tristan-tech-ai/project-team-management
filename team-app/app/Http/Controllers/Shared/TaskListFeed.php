<?php

namespace App\Http\Controllers\Shared;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskWorkflowService;
use App\Support\Codes;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/**
 * Yajra task list feed (Admin: All Tasks, My Tasks, project Task List; Team: My Tasks table view) with server-side filters
 * and quick actions by status (Technical Guide 5.1 "Yajra + subtask progress + quick actions", 6.3 one primary action).
 * Shared by both apps so the columns and rules are the same.
 */
class TaskListFeed
{
    public function send(Builder $q, Request $request): JsonResponse
    {
        foreach (['status' => 'tasks.status', 'task_type' => 'tasks.task_type', 'project_id' => 'tasks.project_id'] as $field => $column) {
            if ($request->filled($field)) {
                $q->where($column, $request->input($field));
            }
        }
        if ($request->filled('assignee')) {
            $q->whereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $request->integer('assignee')));
        }
        if ($request->boolean('overdue')) {
            $q->where('tasks.status', '!=', 'done')->whereNotNull('tasks.due_at')->where('tasks.due_at', '<', now());
        }
        if ($request->filled('keyword')) {
            $q->where('tasks.title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('keyword')).'%');
        }
        // The project PIC and company are loaded so quick-action permissions are computed without a query per row.
        $q->with(['project:id,name,pic_user_id,company_id']);
        $actions = $this->quickActions($request->user());

        return DataTables::eloquent($q)
            ->addColumn('project_name', fn (Task $t) => $t->project?->name ?? '')
            ->addColumn('pic_name', fn (Task $t) => $t->pic?->name)
            ->addColumn('members', fn (Task $t) => $t->assignees->pluck('name')->implode(', '))
            ->addColumn('status_label', fn (Task $t) => ($t->status !== 'done' && $t->due_at?->isPast() ? 'Terlambat · ' : '').Codes::label(Codes::TASK_STATUS, $t->status))
            ->addColumn('status_label_class', fn (Task $t) => $t->status !== 'done' && $t->due_at?->isPast() ? 'late' : $t->status)
            ->addColumn('type', fn (Task $t) => Codes::label(Codes::TASK_TYPE, $t->task_type))
            ->addColumn('subtask', fn (Task $t) => TaskWorkflowService::taskProgress((int) $t->sub_done, (int) $t->sub_total))
            ->addColumn('due', fn (Task $t) => Dates::dateTime($t->due_at))
            ->addColumn('period', fn (Task $t) => $t->period?->name ?? '-')
            ->addColumn('actions', $actions)
            ->orderColumn('due', 'tasks.due_at $1')
            ->toJson();
    }

    /**
     * One primary action per status, the same as the button on the task detail: Start (To Do), Submit result (In Progress), Review (Review),
     * then Open. Permissions follow TaskPolicy (project manager or assignee), computed from data already loaded.
     */
    private function quickActions(?User $u): \Closure
    {
        $super = (bool) $u?->isSuperAdmin();
        $managedCompanies = $u && ! $super && $u->hasRole('manager') ? array_map('intval', $u->activeCompanyIds()) : [];
        $permissions = fn (string $slug) => $u !== null && $u->hasPermission($slug);
        $canMove = $permissions('tasks.move_status');
        $canSubmit = $permissions('tasks.submit');
        $canReview = $permissions('tasks.review');

        return function (Task $t) use ($u, $super, $managedCompanies, $canMove, $canSubmit, $canReview): array {
            $uid = (int) $u?->id;
            $isManager = $super || (int) $t->project?->pic_user_id === $uid || in_array((int) $t->project?->company_id, $managedCompanies, true);
            $isAssignee = (int) $t->pic_user_id === $uid || $t->assignees->contains('id', $uid);
            $open = route('tasks.show', $t);
            $result = [];
            if ($t->status === 'backlog' && $canMove && ($isManager || $isAssignee)) {
                $result[] = ['label' => 'Mulai', 'url' => route('tasks.status', $t), 'method' => 'PATCH', 'fields' => ['status' => 'in_progress']];
            } elseif ($t->status === 'in_progress' && $canSubmit && ($isManager || $isAssignee)) {
                $result[] = ['label' => 'Kirim hasil', 'url' => $open.'#submit-result'];
            } elseif ($t->status === 'review' && $canReview && ($isManager || (int) $t->pic_user_id === $uid)) {
                $result[] = ['label' => 'Tinjau', 'url' => $open.'#review'];
            }
            $result[] = ['label' => 'Buka', 'url' => $open];

            return $result;
        };
    }
}
