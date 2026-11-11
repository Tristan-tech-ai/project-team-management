@php use App\Support\Kode; @endphp
<x-layouts.admin judul="Daftar Project">
    <div class="kepala-halaman">
        <div><div class="remah">Projects</div><h1>Daftar Project</h1><p>Semua project yang bisa kamu lihat, dengan progres task utama.</p></div>
        @can('create', \App\Models\Project::class)<a class="btn btn-primary" href="{{ route('projects.create') }}"><x-ikon name="plus"/> Buat project</a>@endcan
    </div>
    <form id="filter-project" class="kartu mb-3">
        <div class="row g-2">
            <div class="col-6 col-md"><label class="form-label" for="f-tipe">Tipe</label><select id="f-tipe" name="project_type" class="form-select"><option value="">Semua</option>@foreach (Kode::PROJECT_TYPE as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-6 col-md"><label class="form-label" for="f-status">Status</label><select id="f-status" name="status" class="form-select"><option value="">Semua</option>@foreach (Kode::PROJECT_STATUS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-6 col-md"><label class="form-label" for="f-company">Company</label><select id="f-company" name="company_id" class="form-select"><option value="">Semua</option>@foreach ($companies as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-6 col-md"><label class="form-label" for="f-client">Client</label><select id="f-client" name="client_id" class="form-select"><option value="">Semua</option>@foreach ($clients as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-12 col-md"><label class="form-label" for="f-pic">PIC</label><select id="f-pic" name="pic_user_id" class="form-select"><option value="">Semua</option>@foreach ($pics as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        </div>
    </form>
    <div class="kartu"><div class="table-responsive">
        <table id="projects-table" class="table align-middle w-100" data-tabel="{{ route('projects.data') }}" data-filter="#filter-project" data-urut='[[5,"asc"]]'
            data-kolom="{{ json_encode([["data" => "code"], ["data" => "name"], ["data" => "company_name", "name" => "company.name", "orderable" => false], ["data" => "client_name", "name" => "client.name", "orderable" => false],
                ["data" => "project_type", "searchable" => false], ["data" => "due", "name" => "due_at", "searchable" => false], ["data" => "progres", "render" => "progres", "searchable" => false, "orderable" => false],
                ["data" => "status_label", "name" => "status", "render" => "lencana", "searchable" => false], ["data" => "aksi", "render" => "aksi", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr><th scope="col">Kode</th><th scope="col">Nama</th><th scope="col">Company</th><th scope="col">Client</th><th scope="col">Tipe</th><th scope="col">Deadline</th><th scope="col">Progress</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
</x-layouts.admin>
