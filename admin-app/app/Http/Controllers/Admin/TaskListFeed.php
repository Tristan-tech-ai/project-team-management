<?php

namespace App\Http\Controllers\Admin;

use App\Models\Task;
use App\Services\TaskWorkflowService;
use App\Support\Kode;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

/** Feed Yajra daftar task (Semua Task, Task Saya, Task List project) dengan filter server-side. */
class TaskListFeed
{
    public function kirim(Builder $q, Request $request): JsonResponse
    {
        if ($request->filled('status')) {
            $q->where('tasks.status', $request->input('status'));
        }
        if ($request->filled('task_type')) {
            $q->where('tasks.task_type', $request->input('task_type'));
        }
        if ($request->filled('assignee')) {
            $q->whereIn('tasks.id', DB::table('task_assignees')->select('task_id')->where('user_id', $request->integer('assignee')));
        }
        if ($request->boolean('terlambat')) {
            $q->where('tasks.status', '!=', 'done')->whereNotNull('tasks.due_at')->where('tasks.due_at', '<', now());
        }
        if ($request->filled('cari')) {
            $q->where('tasks.title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $request->input('cari')).'%');
        }

        return DataTables::eloquent($q)
            ->addColumn('project_name', fn (Task $t) => $t->project?->name ?? '')
            ->addColumn('pic_name', fn (Task $t) => $t->pic?->name)
            ->addColumn('anggota', fn (Task $t) => $t->assignees->pluck('name')->implode(', '))
            ->addColumn('status_label', fn (Task $t) => ($t->status !== 'done' && $t->due_at?->isPast() ? 'Terlambat · ' : '').Kode::label(Kode::TASK_STATUS, $t->status))
            ->addColumn('status_label_kelas', fn (Task $t) => $t->status !== 'done' && $t->due_at?->isPast() ? 'telat' : $t->status)
            ->addColumn('tipe', fn (Task $t) => Kode::label(Kode::TASK_TYPE, $t->task_type))
            ->addColumn('subtask', fn (Task $t) => TaskWorkflowService::progresTask((int) $t->sub_selesai, (int) $t->sub_total))
            ->addColumn('due', fn (Task $t) => Waktu::tanggalJam($t->due_at))
            ->addColumn('periode', fn (Task $t) => $t->period?->name ?? '-')
            ->addColumn('aksi', fn (Task $t) => [['label' => 'Buka', 'url' => route('tasks.show', $t)]])
            ->orderColumn('due', 'tasks.due_at $1')
            ->toJson();
    }
}
