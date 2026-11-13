<x-layouts.admin :title="$task->title">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('projects.board', $task->project_id) }}">{{ $task->project?->name }}</a> / Task</div><h1>{{ $task->title }}</h1></div>
        <a class="btn btn-outline-secondary" href="{{ route('projects.board', $task->project_id) }}"><x-icon name="arrow-left"/> Kembali ke board</a></div>
    @include('shared.task-detail')
</x-layouts.admin>
