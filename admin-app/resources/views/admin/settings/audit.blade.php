@php use App\Support\Dates; use Illuminate\Support\Str; @endphp
<x-layouts.admin title="Log Aktivitas">
    <div class="page-header"><div><div class="crumb">System Settings</div><h1>Log Aktivitas</h1>
        <p>Semua aksi yang mengubah data di Admin App dan Team App, termasuk masuk dan keluar. Kata sandi dan token tidak pernah disimpan.</p></div></div>
    <form method="get" action="{{ route('settings.audit') }}" class="panel mb-3" style="max-width:640px" role="search">
        <label class="form-label" for="keyword">Cari nama, email, route, atau alamat</label>
        <div class="d-flex gap-2">
            <input id="keyword" name="keyword" class="form-control" value="{{ $keyword }}" maxlength="100">
            <button class="btn btn-outline-secondary" type="submit">Cari</button>
        </div>
    </form>
    <div class="panel">
        <div class="table-responsive" role="region" aria-label="Tabel log aktivitas" tabindex="0">
            <table class="table table-sm align-middle mb-0" style="min-width:900px">
                <thead><tr><th scope="col">Waktu</th><th scope="col">Pengguna</th><th scope="col">Aplikasi</th><th scope="col">Aksi</th><th scope="col">Status</th><th scope="col">Isian</th></tr></thead>
                <tbody>
                @forelse($log as $l)
                    <tr>
                        <td class="text-nowrap">{{ Dates::dateTime($l->created_at) }}</td>
                        <td>{{ $l->user?->name ?? 'Pengguna dihapus' }}<div class="small muted">{{ $l->role ?? '-' }}</div></td>
                        <td>{{ $l->app }}</td>
                        <td><code>{{ $l->method }}</code> {{ $l->route ?? $l->path }}</td>
                        <td>{{ $l->status }}</td>
                        <td class="small muted" style="min-width:260px;max-width:340px;overflow-wrap:anywhere">{{ Str::limit(json_encode($l->input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted">{{ $keyword === '' ? 'Belum ada aksi yang tercatat.' : 'Tidak ada aksi yang cocok dengan pencarian ini.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $log->links() }}</div>
    </div>
</x-layouts.admin>
