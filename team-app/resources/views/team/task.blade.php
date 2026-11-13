{{-- Task detail on the Team App (prototype 7.4): back to My Tasks, then the shared detail (checklist, submit for review). --}}
<x-layouts.team :title="$task->title">
    <a class="d-inline-flex align-items-center gap-1 mb-2" href="{{ route('tasks.index') }}"><x-icon name="arrow-left"/> Kembali ke Task Saya</a>
    <div class="page-header"><div><h1 class="mb-0">{{ $task->title }}</h1>
        <p>Project: <a href="{{ route('projects.board', $task->project_id) }}">{{ $task->project?->name }}</a></p></div></div>
    @include('shared.task-detail')
</x-layouts.team>
