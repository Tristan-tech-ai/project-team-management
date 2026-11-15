@php use App\Support\Codes; @endphp
<x-layouts.admin :title="$title">
    <div class="page-header"><div><div class="crumb">Aktivitas</div><h1>{{ $title }}</h1><p>{{ $description }}</p></div></div>
    <form id="filter-activities" class="panel mb-3"><div class="row g-2">
        <div class="col-md-6"><label class="form-label" for="fa-p">Project</label><select id="fa-p" name="project_id" class="form-select"><option value="">Semua project</option>@foreach($projects as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label" for="fa-d">Dampak</label><select id="fa-d" name="impact_type" class="form-select"><option value="">Semua</option>@foreach(Codes::IMPACT_TYPE as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    </div></form>
    <div class="panel"><div class="table-responsive">
        <table class="table align-middle w-100" data-table="{{ $feed }}" data-filter="#filter-activities" data-order='[[0,"desc"]]'
            data-table-columns="{{ json_encode([["data" => "date", "searchable" => false], ["data" => "kind", "name" => "activity_type", "searchable" => false], ["data" => "title"], ["data" => "project_name", "name" => "project.name", "orderable" => false],
                ["data" => "participants", "searchable" => false, "orderable" => false], ["data" => "duration", "name" => "duration_minutes", "searchable" => false],
                ["data" => "impact", "name" => "impact_type", "render" => "badge", "searchable" => false], ["data" => "status_label", "name" => "status", "render" => "badge", "searchable" => false],
                ["data" => "actions", "render" => "actions", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr><th scope="col">Tanggal</th><th scope="col">Jenis</th><th scope="col">Judul</th><th scope="col">Project</th><th scope="col">Peserta</th><th scope="col">Durasi</th><th scope="col">Dampak</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
</x-layouts.admin>
