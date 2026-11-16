{{-- I3 side panel content (GET /tasks/{id}?panel=1): the same detail as the full page, in one column, without the layout. --}}
<div data-task-panel-content data-title="{{ $task->title }}" data-project="{{ $task->project?->name }}" data-url="{{ route('tasks.show', $task) }}" data-status="{{ $task->status }}">
    @include('shared.task-detail', ['panel' => true])
</div>
