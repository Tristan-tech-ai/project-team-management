@php use App\Support\Codes; @endphp
<x-layouts.admin title="Monitoring Project">
    <div class="page-header"><div><div class="crumb">Manajemen</div><h1>Monitoring Project</h1><p>Target awal dibanding deadline terbaru, dan kondisi sesuai jadwal, perlu perhatian, terlambat, atau terhambat.</p></div>
        @if(auth()->user()->hasPermission('management.export'))<a class="btn btn-outline-primary" href="{{ route('management.export', ['kind' => 'monitoring']) }}"><x-icon name="external-link"/> Unduh CSV</a>@endif</div>
    <form class="panel mb-3" method="get" data-auto-submit><div class="row g-2 align-items-end">
        <div class="col-6 col-md"><label class="form-label" for="m-t">Tipe</label><select id="m-t" name="project_type" class="form-select"><option value="">Semua</option>@foreach(Codes::PROJECT_TYPE as $k => $v)<option value="{{ $k }}" @selected(($filter['project_type'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-6 col-md"><label class="form-label" for="m-s">Status</label><select id="m-s" name="status" class="form-select"><option value="">Semua</option>@foreach(Codes::PROJECT_STATUS as $k => $v)<option value="{{ $k }}" @selected(($filter['status'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-6 col-md"><label class="form-label" for="m-c">Company</label><select id="m-c" name="company_id" class="form-select"><option value="">Semua</option>@foreach($companies as $k => $v)<option value="{{ $k }}" @selected(($filter['company_id'] ?? '') == $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-6 col-md"><label class="form-label" for="m-cl">Client</label><select id="m-cl" name="client_id" class="form-select"><option value="">Semua</option>@foreach($clients as $k => $v)<option value="{{ $k }}" @selected(($filter['client_id'] ?? '') == $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-secondary w-100 no-js-only" type="submit"><x-icon name="funnel"/> Saring</button></div>
    </div></form>
    <div class="panel">@include('admin.management._monitor', ['rows' => $rows, 'full' => true])</div>
</x-layouts.admin>
