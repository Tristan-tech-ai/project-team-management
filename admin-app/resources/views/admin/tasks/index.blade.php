<x-layouts.admin :title="$title">
    <div class="page-header"><div><div class="crumb">Tasks</div><h1>{{ $title }}</h1>
        <p>{{ $mine ? 'Task yang kamu pegang sebagai PIC atau pelaksana, termasuk task kolaboratif.' : 'Semua task utama di project yang bisa kamu lihat. Cari, saring, dan urutkan di server.' }}</p></div>
        @can('tasks.create')<a class="btn btn-primary" href="{{ route('tasks.create') }}"><x-icon name="plus"/> Tambah Task</a>@endcan</div>
    @include('admin.tasks._filter', ['id' => 'filter-task', 'people' => $people])
    <div class="panel"><div class="table-responsive">
        <table class="table align-middle w-100" data-table="{{ $feed }}" data-filter="#filter-task" data-order='[[5,"asc"]]'
            data-columns="{{ json_encode([["data" => "title"], ["data" => "project_name", "name" => "project.name", "orderable" => false], ["data" => "pic_name", "name" => "pic.name", "orderable" => false],
                ["data" => "members", "searchable" => false, "orderable" => false], ["data" => "subtask", "render" => "progress", "searchable" => false, "orderable" => false],
                ["data" => "due", "searchable" => false], ["data" => "type", "name" => "task_type", "searchable" => false],
                ["data" => "status_label", "name" => "status", "render" => "badge", "searchable" => false], ["data" => "actions", "render" => "actions", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr><th scope="col">Task</th><th scope="col">Project</th><th scope="col">PIC</th><th scope="col">Pelaksana</th><th scope="col">Checklist</th><th scope="col">Deadline</th><th scope="col">Tipe</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
</x-layouts.admin>
