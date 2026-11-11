@php use App\Support\Kode; @endphp
<x-layouts.admin :judul="$judul">
    <div class="kepala-halaman"><div><div class="remah">Activities</div><h1>{{ $judul }}</h1><p>{{ $ket }}</p></div></div>
    <form id="filter-akt" class="kartu mb-3"><div class="row g-2">
        <div class="col-md-6"><label class="form-label" for="fa-p">Project</label><select id="fa-p" name="project_id" class="form-select"><option value="">Semua project</option>@foreach ($projects as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label" for="fa-d">Dampak</label><select id="fa-d" name="impact_type" class="form-select"><option value="">Semua</option>@foreach (Kode::IMPACT_TYPE as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    </div></form>
    <div class="kartu"><div class="table-responsive">
        <table class="table align-middle w-100" data-tabel="{{ $feed }}" data-filter="#filter-akt" data-urut='[[0,"desc"]]'
            data-kolom="{{ json_encode([["data" => "tanggal", "searchable" => false], ["data" => "jenis", "name" => "activity_type", "searchable" => false], ["data" => "title"], ["data" => "project_name", "name" => "project.name", "orderable" => false],
                ["data" => "peserta", "searchable" => false, "orderable" => false], ["data" => "durasi", "name" => "duration_minutes", "searchable" => false],
                ["data" => "dampak", "name" => "impact_type", "render" => "lencana", "searchable" => false], ["data" => "status_label", "name" => "status", "render" => "lencana", "searchable" => false],
                ["data" => "aksi", "render" => "aksi", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr><th scope="col">Tanggal</th><th scope="col">Jenis</th><th scope="col">Judul</th><th scope="col">Project</th><th scope="col">Peserta</th><th scope="col">Durasi</th><th scope="col">Dampak</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
</x-layouts.admin>
