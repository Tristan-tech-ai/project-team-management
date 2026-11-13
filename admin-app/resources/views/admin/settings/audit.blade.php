@php use App\Support\Waktu; use Illuminate\Support\Str; @endphp
<x-layouts.admin judul="Log Aktivitas">
    <div class="kepala-halaman"><div><div class="remah">System Settings</div><h1>Log Aktivitas</h1>
        <p>Semua aksi yang mengubah data di Admin App dan Team App, termasuk masuk dan keluar. Kata sandi dan token tidak pernah disimpan.</p></div></div>
    <form method="get" action="{{ route('settings.audit') }}" class="kartu mb-3" style="max-width:640px" role="search">
        <label class="form-label" for="cari">Cari nama, email, route, atau alamat</label>
        <div class="d-flex gap-2">
            <input id="cari" name="cari" class="form-control" value="{{ $cari }}" maxlength="100">
            <button class="btn btn-outline-secondary" type="submit">Cari</button>
        </div>
    </form>
    <div class="kartu">
        <div class="table-responsive" role="region" aria-label="Tabel log aktivitas" tabindex="0">
            <table class="table table-sm align-middle mb-0" style="min-width:900px">
                <thead><tr><th scope="col">Waktu</th><th scope="col">Pengguna</th><th scope="col">Aplikasi</th><th scope="col">Aksi</th><th scope="col">Status</th><th scope="col">Isian</th></tr></thead>
                <tbody>
                @forelse ($log as $l)
                    <tr>
                        <td class="text-nowrap">{{ Waktu::tanggalJam($l->created_at) }}</td>
                        <td>{{ $l->user?->name ?? 'Pengguna dihapus' }}<div class="small teks-2">{{ $l->role ?? '-' }}</div></td>
                        <td>{{ $l->app }}</td>
                        <td><code>{{ $l->method }}</code> {{ $l->route ?? $l->path }}</td>
                        <td>{{ $l->status }}</td>
                        <td class="small teks-2" style="min-width:260px;max-width:340px;overflow-wrap:anywhere">{{ Str::limit(json_encode($l->input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="teks-2">{{ $cari === '' ? 'Belum ada aksi yang tercatat.' : 'Tidak ada aksi yang cocok dengan pencarian ini.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $log->links() }}</div>
    </div>
</x-layouts.admin>
