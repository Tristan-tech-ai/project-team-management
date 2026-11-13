@php use App\Support\Codes; @endphp
<div class="page-header">
    <div>
        <div class="crumb"><a href="{{ route('projects.index') }}">Projects</a> / {{ $project->code }}</div>
        <h1 class="mb-1">{{ $project->name }}</h1>
        <p class="mb-0">{{ $project->client?->name ?? 'Project internal' }} · {{ Codes::label(Codes::PROJECT_TYPE, $project->project_type) }} · PIC: {{ $project->pic?->name }}
            · <span class="numeric">{{ $counts['divisions'] }}</span> divisi · <span class="numeric">{{ $counts['people'] }}</span> anggota
            · <x-badge :code="$project->status" :text="Codes::label(Codes::PROJECT_STATUS, $project->status)"/></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($canCreateTask)<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quick-task-{{ $project->id }}"><x-icon name="plus"/> Tambah Task</button>@endif
        @if($canEdit)<a class="btn btn-outline-secondary" href="{{ route('projects.edit', $project) }}"><x-icon name="pencil"/> Ubah</a>@endif
    </div>
</div>
<nav class="project-tabs" aria-label="Bagian project">
    @foreach(['projects.show' => 'Overview', 'projects.board' => 'Kanban Board', 'projects.tasks' => 'Task List', 'projects.team' => 'Project Team', 'projects.activities' => 'Activities', 'projects.calendar' => 'Calendar'] as $r => $l)
        <a href="{{ route($r, $project) }}" @if(request()->routeIs($r)) aria-current="page" @endif>{{ $l }}</a>
    @endforeach
</nav>
@if($canCreateTask)
    @include('shared.quick-task', ['project' => $project, 'memberOptions' => $memberOptions, 'taskTemplates' => $taskTemplates ?? \App\Models\TaskTemplate::query()->where('is_active', 1)->orderBy('name')->pluck('name', 'id'), 'selectedPeriod' => $selectedPeriod ?? null])
    @if($errors->has('title') || $errors->has('assignees') || $errors->has('pic_user_id'))<span data-open-modal="quick-task-{{ $project->id }}" hidden></span>@endif
@endif
