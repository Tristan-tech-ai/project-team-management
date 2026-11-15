<x-layouts.admin title="Tambah Task">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('tasks.index') }}">Task</a></div><h1>Tambah Task</h1>
        <p>Cukup project, judul, PIC, pelaksana, dan deadline; task masuk Antrian. Deskripsi, lampiran, dan checklist bisa dilengkapi nanti.</p></div></div>
    @if($projects->isEmpty())
        <div class="panel" style="max-width:640px"><div class="empty-state"><span class="empty-icon"><x-icon name="folder-plus" :size="22"/></span>
            <strong>Belum ada project aktif yang bisa kamu tambahi task.</strong>
            @can('projects.create')<a class="btn btn-primary mt-2" href="{{ route('projects.create') }}"><x-icon name="plus"/> Buat project</a>@endcan</div></div>
    @else
        @include('shared.task-create-form', ['storeUrl' => route('projects.tasks.store', ['project' => '__ID__'])])
    @endif
</x-layouts.admin>
