@php use App\Support\Dates; use Illuminate\Support\Str; @endphp
<x-layouts.admin title="Log Aktivitas">
    <div class="page-header"><div><div class="crumb">Pengaturan</div><h1>Log Aktivitas</h1>
        <p>Semua aksi yang mengubah data di Admin App dan Team App, termasuk masuk dan keluar. Kata sandi dan token tidak pernah disimpan.</p></div></div>
    <form method="get" action="{{ route('settings.audit') }}" class="panel mb-3" style="max-width:640px" role="search">
        <label class="form-label" for="keyword">Cari nama atau email orang</label>
        <div class="d-flex gap-2">
            <input id="keyword" name="keyword" class="form-control" value="{{ $keyword }}" maxlength="100">
            <button class="btn btn-outline-secondary" type="submit">Cari</button>
        </div>
    </form>
    <div class="panel">
        <div class="table-responsive" role="region" aria-label="Tabel log aktivitas" tabindex="0">
            <table class="table table-sm align-middle mb-0" style="min-width:760px">
                <thead><tr><th scope="col">Waktu</th><th scope="col">Yang terjadi</th><th scope="col">Hasil</th><th scope="col">Detail</th></tr></thead>
                <tbody>
                @forelse($log as $l)
                    @php([$outcome, $tone] = \App\Support\AuditSentence::outcome((int) $l->status))
                    <tr>
                        <td class="text-nowrap small">{{ Dates::dateTime($l->created_at) }}<div class="muted">{{ Dates::relative($l->created_at, past: true) }}</div></td>
                        <td><span class="fw-semibold">{{ $l->user?->name ?? 'Pengguna dihapus' }}</span> {{ \App\Support\AuditSentence::describe($l) }}
                            <div class="small muted">{{ $l->app === 'team' ? 'Team App' : 'Admin App' }}@if($l->role) · {{ ['super_admin' => 'Super Admin', 'manager' => 'Manager', 'member' => 'Member'][$l->role] ?? $l->role }}@endif</div></td>
                        <td><x-badge :code="$tone" :text="$outcome"/></td>
                        <td class="small"><details><summary class="muted">Lihat</summary>
                            <div class="muted mt-1" style="max-width:340px;overflow-wrap:anywhere"><code>{{ $l->method }} {{ $l->path }}</code><br>{{ Str::limit(json_encode($l->input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 240) }}</div></details></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">{{ $keyword === '' ? 'Belum ada aksi yang tercatat.' : 'Tidak ada aksi yang cocok dengan pencarian ini.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $log->links() }}</div>
    </div>
</x-layouts.admin>
