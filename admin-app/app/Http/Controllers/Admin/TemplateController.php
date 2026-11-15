<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use App\Models\TaskTemplate;
use App\Support\Codes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Project Templates (tasks + subtasks to create) and Task Templates (subtask checklist).
 * Task lists are written one per line for speed: "Task title | day offset | subtask; subtask".
 */
class TemplateController extends Controller
{
    public function projects(): View
    {
        abort_unless(auth()->user()->hasPermission('templates.view'), 403);

        return view('admin.templates.index', ['title' => 'Template Project', 'description' => 'Klik template saat membuat project untuk menghasilkan semua task dan subtask-nya.',
            'feed' => route('templates.projects.data'), 'add' => auth()->user()->can('templates.manage') ? route('templates.projects.create') : null, 'kind' => 'project']);
    }

    public function projectsData(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('templates.view'), 403);
        $can = $request->user()->can('templates.manage');
        $canUse = $request->user()->can('projects.create');
        $q = ProjectTemplate::query()->with(['tasks' => fn ($t) => $t->orderBy('sort_order')])->select('project_templates.*');

        return DataTables::eloquent($q)
            ->editColumn('project_type', fn (ProjectTemplate $t) => Codes::label(Codes::PROJECT_TYPE, $t->project_type))
            ->addColumn('content', fn (ProjectTemplate $t) => $t->tasks->whereNull('parent_id')->count().' task, '.$t->tasks->whereNotNull('parent_id')->count().' subtask: '
                .$t->tasks->whereNull('parent_id')->pluck('title')->implode(', '))
            ->addColumn('status', fn (ProjectTemplate $t) => $t->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_class', fn (ProjectTemplate $t) => $t->is_active ? 'ok' : 'neutral')
            ->addColumn('actions', fn (ProjectTemplate $t) => array_values(array_filter([
                $canUse && $t->is_active ? ['label' => 'Pakai', 'url' => route('projects.create', ['template' => $t->id]), 'primary' => true] : null,
                $can ? ['label' => 'Ubah', 'url' => route('templates.projects.edit', $t)] : null,
            ])))
            ->toJson();
    }

    public function tasks(): View
    {
        abort_unless(auth()->user()->hasPermission('templates.view'), 403);

        return view('admin.templates.index', ['title' => 'Template Task', 'description' => 'Task siap pakai beserta checklist subtask-nya, dipilih saat Quick Add Task.',
            'feed' => route('templates.tasks.data'), 'add' => auth()->user()->can('templates.manage') ? route('templates.tasks.create') : null, 'kind' => 'task']);
    }

    public function tasksData(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('templates.view'), 403);
        $can = $request->user()->can('templates.manage');
        $q = TaskTemplate::query()->with(['subtasks' => fn ($s) => $s->orderBy('sort_order')])->select('task_templates.*');

        return DataTables::eloquent($q)
            ->editColumn('task_type', fn (TaskTemplate $t) => Codes::label(Codes::TASK_TYPE, $t->task_type))
            ->addColumn('content', fn (TaskTemplate $t) => $t->subtasks->count().' subtask: '.$t->subtasks->pluck('title')->implode(', '))
            ->addColumn('status', fn (TaskTemplate $t) => $t->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_class', fn (TaskTemplate $t) => $t->is_active ? 'ok' : 'neutral')
            ->addColumn('actions', fn (TaskTemplate $t) => $can ? [['label' => 'Ubah', 'url' => route('templates.tasks.edit', $t)]] : [])
            ->toJson();
    }

    public function createProject(): View
    {
        $this->authorize('templates.manage');

        return view('admin.templates.project-form', ['template' => new ProjectTemplate(['project_type' => 'one_time', 'is_active' => true]), 'rows' => '']);
    }

    public function editProject(ProjectTemplate $template): View
    {
        $this->authorize('templates.manage');
        $all = $template->tasks()->orderBy('sort_order')->orderBy('id')->get();
        $rows = $all->whereNull('parent_id')->map(fn ($t) => trim($t->title.' | '.($t->relative_due_days ?? '').' | '.$all->where('parent_id', $t->id)->pluck('title')->implode('; '), ' |'))->implode("\n");

        return view('admin.templates.project-form', ['template' => $template, 'rows' => $rows]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $tasks] = $this->readProject($request);
        DB::transaction(function () use ($d, $tasks, $request) {
            $t = ProjectTemplate::create($d + ['created_by' => $request->user()->id]);
            $this->taskContent($t, $tasks);
        });

        return redirect()->route('templates.projects.index')->with('ok', 'Template project tersimpan.');
    }

    public function updateProject(Request $request, ProjectTemplate $template): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $tasks] = $this->readProject($request, $template);
        DB::transaction(function () use ($d, $tasks, $template) {
            $template->update($d);
            // Templates are only blueprints: changing the task list does not change projects created earlier.
            $template->tasks()->whereNotNull('parent_id')->delete();
            $template->tasks()->delete();
            $this->taskContent($template, $tasks);
        });

        return redirect()->route('templates.projects.index')->with('ok', 'Template project tersimpan. Project yang sudah dibuat tidak berubah.');
    }

    public function createTask(): View
    {
        $this->authorize('templates.manage');

        return view('admin.templates.task-form', ['template' => new TaskTemplate(['task_type' => 'general', 'is_active' => true]), 'rows' => '']);
    }

    public function editTask(TaskTemplate $template): View
    {
        $this->authorize('templates.manage');

        return view('admin.templates.task-form', ['template' => $template, 'rows' => $template->subtasks()->orderBy('sort_order')->pluck('title')->implode("\n")]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $sub] = $this->readTask($request);
        DB::transaction(function () use ($d, $sub, $request) {
            $t = TaskTemplate::create($d + ['created_by' => $request->user()->id]);
            foreach ($sub as $i => $title) {
                $t->subtasks()->create(['title' => $title, 'sort_order' => $i]);
            }
        });

        return redirect()->route('templates.tasks.index')->with('ok', 'Template task tersimpan.');
    }

    public function updateTask(Request $request, TaskTemplate $template): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $sub] = $this->readTask($request, $template);
        DB::transaction(function () use ($d, $sub, $template) {
            $template->update($d);
            $template->subtasks()->delete();
            foreach ($sub as $i => $title) {
                $template->subtasks()->create(['title' => $title, 'sort_order' => $i]);
            }
        });

        return redirect()->route('templates.tasks.index')->with('ok', 'Template task tersimpan.');
    }

    /** @return array{0:array, 1:array<int,array{title:string,days:?int,sub:array<int,string>}>} */
    private function readProject(Request $request, ?ProjectTemplate $t = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200', Rule::unique('project_templates', 'name')->ignore($t?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_type' => ['required', Rule::in(array_keys(Codes::PROJECT_TYPE))],
            'default_estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
            'rows' => ['required', 'string', 'max:20000'],
        ]);
        $tasks = [];
        foreach (preg_split('/\R/', $d['rows']) as $no => $l) {
            if (trim($l) === '') {
                continue;
            }
            $bag = array_map('trim', explode('|', $l));
            if ($bag[0] === '' || mb_strlen($bag[0]) > 255) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rows' => 'Baris '.($no + 1).': judul task wajib dan paling banyak 255 karakter.']);
            }
            $days = isset($bag[1]) && $bag[1] !== '' ? $bag[1] : null;
            if ($days !== null && (! ctype_digit($days) || (int) $days > 3650)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['rows' => 'Baris '.($no + 1).': hari ke- harus angka 0 sampai 3650.']);
            }
            $sub = isset($bag[2]) ? array_values(array_filter(array_map('trim', explode(';', $bag[2])), fn ($s) => $s !== '')) : [];
            $tasks[] = ['title' => $bag[0], 'days' => $days === null ? null : (int) $days, 'sub' => array_map(fn ($s) => mb_substr($s, 0, 255), $sub)];
        }
        if ($tasks === [] || count($tasks) > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['rows' => 'Isi 1 sampai 100 task, satu per baris.']);
        }
        unset($d['rows']);
        $d['is_active'] = $request->boolean('is_active');

        return [$d, $tasks];
    }

    private function taskContent(ProjectTemplate $t, array $tasks): void
    {
        foreach ($tasks as $i => $x) {
            $parent = $t->tasks()->create(['parent_id' => null, 'title' => $x['title'], 'task_type' => 'general', 'relative_due_days' => $x['days'], 'sort_order' => $i]);
            foreach ($x['sub'] as $j => $s) {
                $t->tasks()->create(['parent_id' => $parent->id, 'title' => $s, 'task_type' => 'general', 'sort_order' => $j]);
            }
        }
    }

    private function readTask(Request $request, ?TaskTemplate $t = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200', Rule::unique('task_templates', 'name')->ignore($t?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'task_type' => ['required', Rule::in(array_keys(Codes::TASK_TYPE))],
            'default_estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'rows' => ['nullable', 'string', 'max:10000'],
        ]);
        $sub = array_values(array_filter(array_map(fn ($s) => mb_substr(trim($s), 0, 255), preg_split('/\R/', (string) ($d['rows'] ?? ''))), fn ($s) => $s !== ''));
        unset($d['rows']);
        $d['is_active'] = $request->boolean('is_active');

        return [$d, array_slice($sub, 0, 50)];
    }
}
