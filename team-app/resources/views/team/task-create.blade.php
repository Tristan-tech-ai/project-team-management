@php use App\Support\Dates; @endphp
<x-layouts.team title="Tambah task">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('tasks.index') }}">Task</a></div><h1 class="mb-0">Tambah task</h1><p>Cukup project, judul, PIC, pelaksana, dan deadline. Sisanya bisa dilengkapi nanti.</p></div></div>
    @if($projects->isEmpty())
        <div class="panel"><div class="empty-state"><span class="empty-icon"><x-icon name="folder-plus" :size="22"/></span>
            <strong>Kamu belum bisa menambah task.</strong><span>Kamu belum menjadi anggota project aktif. Minta manager menambahkanmu ke tim project.</span></div></div>
    @else
        @include('shared.task-create-form', ['storeUrl' => route('projects.tasks.store', ['project' => '__ID__']), 'fullWidth' => true])
    @endif
</x-layouts.team>
