<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProjectTemplate;
use App\Models\TaskTemplate;
use App\Support\Kode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

/**
 * Template Project (task + subtask yang akan dibuat) dan Template Task (checklist subtask).
 * Daftar task ditulis per baris supaya cepat: "Judul task | hari ke- | subtask; subtask".
 */
class TemplateController extends Controller
{
    public function projects(): View
    {
        abort_unless(auth()->user()->hasPermission('templates.view'), 403);

        return view('admin.templates.index', ['judul' => 'Template Project', 'ket' => 'Klik template saat membuat project untuk menghasilkan semua task dan subtask-nya.',
            'feed' => route('templates.projects.data'), 'tambah' => auth()->user()->can('templates.manage') ? route('templates.projects.create') : null, 'jenis' => 'project']);
    }

    public function projectsData(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('templates.view'), 403);
        $boleh = $request->user()->can('templates.manage');
        $q = ProjectTemplate::query()->with(['tasks' => fn ($t) => $t->orderBy('sort_order')])->select('project_templates.*');

        return DataTables::eloquent($q)
            ->editColumn('project_type', fn (ProjectTemplate $t) => Kode::label(Kode::PROJECT_TYPE, $t->project_type))
            ->addColumn('isi', fn (ProjectTemplate $t) => $t->tasks->whereNull('parent_id')->count().' task, '.$t->tasks->whereNotNull('parent_id')->count().' subtask: '
                .$t->tasks->whereNull('parent_id')->pluck('title')->implode(', '))
            ->addColumn('status', fn (ProjectTemplate $t) => $t->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_kelas', fn (ProjectTemplate $t) => $t->is_active ? 'ok' : 'netral')
            ->addColumn('aksi', fn (ProjectTemplate $t) => $boleh ? [['label' => 'Ubah', 'url' => route('templates.projects.edit', $t)]] : [])
            ->toJson();
    }

    public function tasks(): View
    {
        abort_unless(auth()->user()->hasPermission('templates.view'), 403);

        return view('admin.templates.index', ['judul' => 'Template Task', 'ket' => 'Task siap pakai beserta checklist subtask-nya, dipilih saat Quick Add Task.',
            'feed' => route('templates.tasks.data'), 'tambah' => auth()->user()->can('templates.manage') ? route('templates.tasks.create') : null, 'jenis' => 'task']);
    }

    public function tasksData(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('templates.view'), 403);
        $boleh = $request->user()->can('templates.manage');
        $q = TaskTemplate::query()->with(['subtasks' => fn ($s) => $s->orderBy('sort_order')])->select('task_templates.*');

        return DataTables::eloquent($q)
            ->editColumn('task_type', fn (TaskTemplate $t) => Kode::label(Kode::TASK_TYPE, $t->task_type))
            ->addColumn('isi', fn (TaskTemplate $t) => $t->subtasks->count().' subtask: '.$t->subtasks->pluck('title')->implode(', '))
            ->addColumn('status', fn (TaskTemplate $t) => $t->is_active ? 'Aktif' : 'Nonaktif')
            ->addColumn('status_kelas', fn (TaskTemplate $t) => $t->is_active ? 'ok' : 'netral')
            ->addColumn('aksi', fn (TaskTemplate $t) => $boleh ? [['label' => 'Ubah', 'url' => route('templates.tasks.edit', $t)]] : [])
            ->toJson();
    }

    public function createProject(): View
    {
        $this->authorize('templates.manage');

        return view('admin.templates.project-form', ['template' => new ProjectTemplate(['project_type' => 'one_time', 'is_active' => true]), 'baris' => '']);
    }

    public function editProject(ProjectTemplate $template): View
    {
        $this->authorize('templates.manage');
        $semua = $template->tasks()->orderBy('sort_order')->orderBy('id')->get();
        $baris = $semua->whereNull('parent_id')->map(fn ($t) => trim($t->title.' | '.($t->relative_due_days ?? '').' | '.$semua->where('parent_id', $t->id)->pluck('title')->implode('; '), ' |'))->implode("\n");

        return view('admin.templates.project-form', ['template' => $template, 'baris' => $baris]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $tasks] = $this->bacaProject($request);
        DB::transaction(function () use ($d, $tasks, $request) {
            $t = ProjectTemplate::create($d + ['created_by' => $request->user()->id]);
            $this->isiTasks($t, $tasks);
        });

        return redirect()->route('templates.projects.index')->with('ok', 'Template project tersimpan.');
    }

    public function updateProject(Request $request, ProjectTemplate $template): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $tasks] = $this->bacaProject($request, $template);
        DB::transaction(function () use ($d, $tasks, $template) {
            $template->update($d);
            // Template hanya cetak biru: mengganti daftar task tidak mengubah project yang sudah dibuat.
            $template->tasks()->whereNotNull('parent_id')->delete();
            $template->tasks()->delete();
            $this->isiTasks($template, $tasks);
        });

        return redirect()->route('templates.projects.index')->with('ok', 'Template project tersimpan. Project yang sudah dibuat tidak berubah.');
    }

    public function createTask(): View
    {
        $this->authorize('templates.manage');

        return view('admin.templates.task-form', ['template' => new TaskTemplate(['task_type' => 'general', 'is_active' => true]), 'baris' => '']);
    }

    public function editTask(TaskTemplate $template): View
    {
        $this->authorize('templates.manage');

        return view('admin.templates.task-form', ['template' => $template, 'baris' => $template->subtasks()->orderBy('sort_order')->pluck('title')->implode("\n")]);
    }

    public function storeTask(Request $request): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $sub] = $this->bacaTask($request);
        DB::transaction(function () use ($d, $sub, $request) {
            $t = TaskTemplate::create($d + ['created_by' => $request->user()->id]);
            foreach ($sub as $i => $judul) {
                $t->subtasks()->create(['title' => $judul, 'sort_order' => $i]);
            }
        });

        return redirect()->route('templates.tasks.index')->with('ok', 'Template task tersimpan.');
    }

    public function updateTask(Request $request, TaskTemplate $template): RedirectResponse
    {
        $this->authorize('templates.manage');
        [$d, $sub] = $this->bacaTask($request, $template);
        DB::transaction(function () use ($d, $sub, $template) {
            $template->update($d);
            $template->subtasks()->delete();
            foreach ($sub as $i => $judul) {
                $template->subtasks()->create(['title' => $judul, 'sort_order' => $i]);
            }
        });

        return redirect()->route('templates.tasks.index')->with('ok', 'Template task tersimpan.');
    }

    /** @return array{0:array, 1:array<int,array{title:string,hari:?int,sub:array<int,string>}>} */
    private function bacaProject(Request $request, ?ProjectTemplate $t = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200', Rule::unique('project_templates', 'name')->ignore($t?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'project_type' => ['required', Rule::in(array_keys(Kode::PROJECT_TYPE))],
            'default_estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
            'baris' => ['required', 'string', 'max:20000'],
        ]);
        $tasks = [];
        foreach (preg_split('/\R/', $d['baris']) as $no => $l) {
            if (trim($l) === '') {
                continue;
            }
            $bag = array_map('trim', explode('|', $l));
            if ($bag[0] === '' || mb_strlen($bag[0]) > 255) {
                throw \Illuminate\Validation\ValidationException::withMessages(['baris' => 'Baris '.($no + 1).': judul task wajib dan paling banyak 255 karakter.']);
            }
            $hari = isset($bag[1]) && $bag[1] !== '' ? $bag[1] : null;
            if ($hari !== null && (! ctype_digit($hari) || (int) $hari > 3650)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['baris' => 'Baris '.($no + 1).': hari ke- harus angka 0 sampai 3650.']);
            }
            $sub = isset($bag[2]) ? array_values(array_filter(array_map('trim', explode(';', $bag[2])), fn ($s) => $s !== '')) : [];
            $tasks[] = ['title' => $bag[0], 'hari' => $hari === null ? null : (int) $hari, 'sub' => array_map(fn ($s) => mb_substr($s, 0, 255), $sub)];
        }
        if ($tasks === [] || count($tasks) > 100) {
            throw \Illuminate\Validation\ValidationException::withMessages(['baris' => 'Isi 1 sampai 100 task, satu per baris.']);
        }
        unset($d['baris']);
        $d['is_active'] = $request->boolean('is_active');

        return [$d, $tasks];
    }

    private function isiTasks(ProjectTemplate $t, array $tasks): void
    {
        foreach ($tasks as $i => $x) {
            $induk = $t->tasks()->create(['parent_id' => null, 'title' => $x['title'], 'task_type' => 'general', 'relative_due_days' => $x['hari'], 'sort_order' => $i]);
            foreach ($x['sub'] as $j => $s) {
                $t->tasks()->create(['parent_id' => $induk->id, 'title' => $s, 'task_type' => 'general', 'sort_order' => $j]);
            }
        }
    }

    private function bacaTask(Request $request, ?TaskTemplate $t = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:200', Rule::unique('task_templates', 'name')->ignore($t?->id)->whereNull('deleted_at')],
            'description' => ['nullable', 'string', 'max:5000'],
            'task_type' => ['required', Rule::in(array_keys(Kode::TASK_TYPE))],
            'default_estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'baris' => ['nullable', 'string', 'max:10000'],
        ]);
        $sub = array_values(array_filter(array_map(fn ($s) => mb_substr(trim($s), 0, 255), preg_split('/\R/', (string) ($d['baris'] ?? ''))), fn ($s) => $s !== ''));
        unset($d['baris']);
        $d['is_active'] = $request->boolean('is_active');

        return [$d, array_slice($sub, 0, 50)];
    }
}
