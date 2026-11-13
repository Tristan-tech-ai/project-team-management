@php use App\Support\Codes; @endphp
<div class="page-header">
    <div><div class="crumb"><a href="{{ route('projects.index') }}">Project Saya</a></div><h1 class="mb-0">{{ $project->name }}</h1>
        <p>{{ Codes::label(Codes::PROJECT_TYPE, $project->project_type) }} · PIC {{ $project->pic?->name }} · {{ $counts['people'] }} anggota</p></div>
    @if($canCreateTask)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quick-task-{{ $project->id }}"><x-icon name="plus"/> Task</button>@endif
</div>
<nav class="project-tabs" aria-label="Bagian project">
    <a href="{{ route('projects.board', $project) }}" @if(request()->routeIs('projects.board')) aria-current="page" @endif>Board</a>
    <a href="{{ route('projects.show', $project) }}" @if(request()->routeIs('projects.show')) aria-current="page" @endif>Ringkasan</a>
    @can('createActivity', $project)<a href="{{ route('projects.activities.create', $project) }}" @if(request()->routeIs('projects.activities.*')) aria-current="page" @endif>Tambah aktivitas</a>@endcan
</nav>
@if($canCreateTask)
    @include('shared.quick-task', ['project' => $project, 'memberOptions' => $memberOptions, 'taskTemplates' => $taskTemplates ?? collect(), 'selectedPeriod' => $selectedPeriod ?? null])
    @if($errors->has('title') || $errors->has('assignees'))<span data-open-modal="quick-task-{{ $project->id }}" hidden></span>@endif
@endif
