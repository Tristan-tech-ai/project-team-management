<x-layouts.admin :title="$title">
    <div class="page-header">
        <div><div class="crumb">Master Data</div><h1>{{ $title }}</h1><p>{{ $description }}</p></div>
        @if($add)<a href="{{ $add }}" class="btn btn-primary"><x-icon name="plus"/> Tambah {{ mb_strtolower($title) }}</a>@endif
    </div>
    <div class="panel">
        <div class="table-responsive">
            <table class="table align-middle w-100" data-table="{{ $data }}" data-columns="{{ json_encode(collect($columns)->map(fn ($k) => collect($k)->except('title'))->values()) }}">
                <thead><tr>@foreach($columns as $k)<th scope="col" class="{{ $k['cssClass'] ?? '' }}">{{ $k['title'] }}@if($k['title'] === '')<span class="visually-hidden">Aksi</span>@endif</th>@endforeach</tr></thead>
            </table>
        </div>
    </div>
</x-layouts.admin>
