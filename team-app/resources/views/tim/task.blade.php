<x-layouts.tim :judul="$task->title">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('projects.board', $task->project_id) }}">{{ $task->project?->name }}</a></div><h1 class="mb-0">{{ $task->title }}</h1></div></div>
    @include('bersama.task-detail')
</x-layouts.tim>
