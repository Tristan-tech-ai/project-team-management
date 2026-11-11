@php use App\Support\Kode; @endphp
<div class="kepala-halaman">
    <div><div class="remah"><a href="{{ route('projects.index') }}">Project Saya</a></div><h1 class="mb-0">{{ $project->name }}</h1>
        <p>{{ Kode::label(Kode::PROJECT_TYPE, $project->project_type) }} · PIC {{ $project->pic?->name }} · {{ $hitung['orang'] }} anggota</p></div>
    @if ($bolehTask)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quick-task-{{ $project->id }}"><x-ikon name="plus"/> Task</button>@endif
</div>
<nav class="tab-project" aria-label="Bagian project">
    <a href="{{ route('projects.board', $project) }}" @if(request()->routeIs('projects.board')) aria-current="page" @endif>Board</a>
    <a href="{{ route('projects.show', $project) }}" @if(request()->routeIs('projects.show')) aria-current="page" @endif>Ringkasan</a>
    @can('createActivity', $project)<a href="{{ route('projects.activities.create', $project) }}" @if(request()->routeIs('projects.activities.*')) aria-current="page" @endif>Tambah aktivitas</a>@endcan
</nav>
@if ($bolehTask)
    @include('bersama.quick-task', ['project' => $project, 'anggotaPilihan' => $anggotaPilihan, 'templatesTask' => $templatesTask ?? collect(), 'periodeDipilih' => $periodeDipilih ?? null])
    @if ($errors->has('title') || $errors->has('assignees'))<span data-buka-modal="quick-task-{{ $project->id }}" hidden></span>@endif
@endif
