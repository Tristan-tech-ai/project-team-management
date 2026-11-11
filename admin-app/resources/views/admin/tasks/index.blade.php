<x-layouts.admin :judul="$judul">
    <div class="kepala-halaman"><div><div class="remah">Tasks</div><h1>{{ $judul }}</h1>
        <p>{{ $saya ? 'Task yang kamu pegang sebagai PIC atau pelaksana, termasuk task kolaboratif.' : 'Semua task utama di project yang bisa kamu lihat. Cari, saring, dan urutkan di server.' }}</p></div></div>
    @include('admin.tasks._filter', ['id' => 'filter-task', 'orang' => $orang])
    <div class="kartu"><div class="table-responsive">
        <table class="table align-middle w-100" data-tabel="{{ $feed }}" data-filter="#filter-task" data-urut='[[5,"asc"]]'
            data-kolom="{{ json_encode([["data" => "title"], ["data" => "project_name", "name" => "project.name", "orderable" => false], ["data" => "pic_name", "name" => "pic.name", "orderable" => false],
                ["data" => "anggota", "searchable" => false, "orderable" => false], ["data" => "subtask", "render" => "progres", "searchable" => false, "orderable" => false],
                ["data" => "due", "searchable" => false], ["data" => "tipe", "name" => "task_type", "searchable" => false],
                ["data" => "status_label", "name" => "status", "render" => "lencana", "searchable" => false], ["data" => "aksi", "render" => "aksi", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr><th scope="col">Task</th><th scope="col">Project</th><th scope="col">PIC</th><th scope="col">Pelaksana</th><th scope="col">Checklist</th><th scope="col">Deadline</th><th scope="col">Tipe</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
</x-layouts.admin>
