@php use App\Support\Kode; @endphp
<x-layouts.admin :judul="'Task '.$project->name">
    @include('admin.projects._kepala')
    @include('admin.tasks._filter', ['id' => 'filter-ptask', 'orang' => $anggotaPilihan])
    <div class="kartu"><div class="table-responsive">
        <table class="table align-middle w-100" data-tabel="{{ route('projects.tasks.data', $project) }}" data-filter="#filter-ptask" data-urut='[[4,"asc"]]'
            data-kolom="{{ json_encode([["data" => "title"], ["data" => "pic_name", "name" => "pic.name", "orderable" => false], ["data" => "anggota", "searchable" => false, "orderable" => false],
                ["data" => "subtask", "render" => "progres", "searchable" => false, "orderable" => false], ["data" => "due", "searchable" => false], ["data" => "periode", "searchable" => false, "orderable" => false],
                ["data" => "status_label", "name" => "status", "render" => "lencana", "searchable" => false], ["data" => "aksi", "render" => "aksi", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr><th scope="col">Task</th><th scope="col">PIC</th><th scope="col">Pelaksana</th><th scope="col">Checklist</th><th scope="col">Deadline</th><th scope="col">Periode</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
</x-layouts.admin>
