<x-layouts.admin :judul="$judul">
    <div class="kepala-halaman"><div><div class="remah">Templates</div><h1>{{ $judul }}</h1><p>{{ $ket }}</p></div>
        @if ($tambah)<a href="{{ $tambah }}" class="btn btn-primary"><x-ikon name="plus"/> Tambah template</a>@endif</div>
    <div class="kartu"><div class="table-responsive">
        @if ($jenis === 'project')
            <table class="table align-middle w-100" data-tabel="{{ $feed }}" data-kolom="{{ json_encode([["data" => "name"], ["data" => "project_type", "searchable" => false], ["data" => "isi", "searchable" => false, "orderable" => false],
                ["data" => "status", "name" => "is_active", "render" => "lencana", "searchable" => false], ["data" => "aksi", "render" => "aksi", "searchable" => false, "orderable" => false]]) }}">
                <thead><tr><th scope="col">Template</th><th scope="col">Tipe</th><th scope="col">Task yang dihasilkan</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead></table>
        @else
            <table class="table align-middle w-100" data-tabel="{{ $feed }}" data-kolom="{{ json_encode([["data" => "name"], ["data" => "task_type", "searchable" => false], ["data" => "isi", "searchable" => false, "orderable" => false],
                ["data" => "status", "name" => "is_active", "render" => "lencana", "searchable" => false], ["data" => "aksi", "render" => "aksi", "searchable" => false, "orderable" => false]]) }}">
                <thead><tr><th scope="col">Template</th><th scope="col">Jenis</th><th scope="col">Checklist</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead></table>
        @endif
    </div></div>
</x-layouts.admin>
