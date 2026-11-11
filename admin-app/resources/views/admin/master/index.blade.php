<x-layouts.admin :judul="$judul">
    <div class="kepala-halaman">
        <div><div class="remah">Master Data</div><h1>{{ $judul }}</h1><p>{{ $ket }}</p></div>
        @if ($tambah)<a href="{{ $tambah }}" class="btn btn-primary"><x-ikon name="plus"/> Tambah {{ mb_strtolower($judul) }}</a>@endif
    </div>
    <div class="kartu">
        <div class="table-responsive">
            <table class="table align-middle w-100" data-tabel="{{ $data }}" data-kolom="{{ json_encode(collect($kolom)->map(fn ($k) => collect($k)->except('judul'))->values()) }}">
                <thead><tr>@foreach ($kolom as $k)<th scope="col" class="{{ $k['kelas'] ?? '' }}">{{ $k['judul'] }}@if($k['judul'] === '')<span class="visually-hidden">Aksi</span>@endif</th>@endforeach</tr></thead>
            </table>
        </div>
    </div>
</x-layouts.admin>
