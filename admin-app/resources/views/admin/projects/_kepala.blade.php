@php use App\Support\Kode; @endphp
<div class="kepala-halaman">
    <div>
        <div class="remah"><a href="{{ route('projects.index') }}">Projects</a> / {{ $project->code }}</div>
        <h1 class="mb-1">{{ $project->name }}</h1>
        <p class="mb-0">{{ $project->client?->name ?? 'Project internal' }} · {{ Kode::label(Kode::PROJECT_TYPE, $project->project_type) }} · PIC: {{ $project->pic?->name }}
            · <span class="angka">{{ $hitung['divisi'] }}</span> divisi · <span class="angka">{{ $hitung['orang'] }}</span> anggota
            · <x-lencana :kode="$project->status" :teks="Kode::label(Kode::PROJECT_STATUS, $project->status)"/></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if ($bolehTask)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quick-task-{{ $project->id }}"><x-ikon name="plus"/> Tambah Task</button>@endif
        @if ($bolehUbah)<a class="btn btn-outline-secondary" href="{{ route('projects.edit', $project) }}"><x-ikon name="pencil"/> Ubah</a>@endif
    </div>
</div>
<nav class="tab-project" aria-label="Bagian project">
    @foreach (['projects.show' => 'Overview', 'projects.board' => 'Kanban Board', 'projects.tasks' => 'Task List', 'projects.team' => 'Project Team', 'projects.activities' => 'Activities', 'projects.calendar' => 'Calendar'] as $r => $l)
        <a href="{{ route($r, $project) }}" @if(request()->routeIs($r)) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
@if ($bolehTask)
    @include('bersama.quick-task', ['project' => $project, 'anggotaPilihan' => $anggotaPilihan, 'templatesTask' => $templatesTask ?? \App\Models\TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'), 'periodeDipilih' => $periodeDipilih ?? null])
    @if ($errors->has('title') || $errors->has('assignees') || $errors->has('pic_user_id'))<span data-buka-modal="quick-task-{{ $project->id }}" hidden></span>@endif
@endif
