<x-layouts.admin :title="$title">
    <div class="page-header">
        <div><div class="crumb">Data Master</div><h1>{{ $title }}</h1><p>{{ $description }}</p></div>
        @if($add)<a href="{{ $add }}" class="btn btn-primary"><x-icon name="plus"/> Tambah {{ mb_strtolower($title) }}</a>@endif
    </div>
    @if($first = session('first_password'))
        {{-- I12: shown once, right after the account was created. --}}
        <div class="panel first-password mb-3" role="status">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <span class="stat-icon"><x-icon name="key-round"/></span>
                <div class="flex-grow-1 min-w-0"><div class="fw-semibold">Akun {{ $first['name'] }} siap dipakai</div>
                    <div class="small muted">Masuk dengan {{ $first['email'] }} dan sandi awal di samping. Sandi ini hanya tampil sekali; minta {{ $first['name'] }} menggantinya di Profil.</div></div>
                <code class="first-password-value" data-copy-source>{{ $first['password'] }}</code>
                <button type="button" class="btn btn-outline-primary btn-sm" data-copy-button><x-icon name="copy" :size="16"/> Salin</button>
            </div>
        </div>
    @endif
    <div class="panel">
        <div class="table-responsive">
            <table class="table align-middle w-100" data-table="{{ $data }}" data-table-columns="{{ json_encode(collect($columns)->map(fn ($k) => collect($k)->except('title'))->values()) }}">
                <thead><tr>@foreach($columns as $k)<th scope="col" class="{{ $k['cssClass'] ?? '' }}">{{ $k['title'] }}@if($k['title'] === '')<span class="visually-hidden">Aksi</span>@endif</th>@endforeach</tr></thead>
            </table>
        </div>
    </div>
</x-layouts.admin>
