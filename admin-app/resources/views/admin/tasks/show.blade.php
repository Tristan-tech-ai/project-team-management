<x-layouts.admin :judul="$task->title">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('projects.board', $task->project_id) }}">{{ $task->project?->name }}</a> / Task</div><h1>{{ $task->title }}</h1></div>
        <a class="btn btn-outline-secondary" href="{{ route('projects.board', $task->project_id) }}"><x-ikon name="arrow-left"/> Kembali ke board</a></div>
    @include('bersama.task-detail')
</x-layouts.admin>
