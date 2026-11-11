@php use App\Support\Kode; use App\Support\Waktu; use Illuminate\Support\Carbon; @endphp
<x-layouts.admin judul="Laporan Operasional">
    <div class="kepala-halaman"><div><div class="remah">Management</div><h1>Laporan Operasional</h1><p>Periode, export, dan jejak perubahan jadwal dengan bukti aktivitas, peserta, dampak, dan keputusan.</p></div></div>
    <form method="get" class="kartu mb-3"><div class="row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label" for="r-d">Dari</label><input id="r-d" type="date" name="dari" class="form-control" value="{{ $dari->toDateString() }}"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="r-s">Sampai</label><input id="r-s" type="date" name="sampai" class="form-control" value="{{ $sampai->toDateString() }}"></div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-secondary" type="submit">Tampilkan</button></div>
        @if ($bolehExport)
            <div class="col-12 col-md-auto ms-md-auto d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary" href="{{ route('management.export', ['jenis' => 'jadwal', 'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]) }}">Export jejak jadwal</a>
                <a class="btn btn-outline-primary" href="{{ route('management.export', ['jenis' => 'monitoring']) }}">Export monitoring</a>
                <a class="btn btn-outline-primary" href="{{ route('management.export', ['jenis' => 'anggota']) }}">Export beban anggota</a>
            </div>
        @endif
    </div></form>
    <div class="kartu mb-3"><h2 class="kartu-judul">Jejak perubahan jadwal</h2>
        <div class="table-responsive"><table class="table align-middle small">
            <thead><tr><th scope="col">Project / task</th><th scope="col">Target awal</th><th scope="col">Sebelum</th><th scope="col">Usulan</th><th scope="col">Alasan</th><th scope="col">Aktivitas</th><th scope="col">Status</th><th scope="col">Pengaju / pemutus</th></tr></thead>
            <tbody>@forelse ($jejak as $j)
                <tr><td><strong>{{ $j->project }}</strong><div>{{ $j->task ?? 'Deadline project' }}</div></td><td>{{ $j->baseline ? Waktu::tanggal(Carbon::parse($j->baseline)) : '-' }}</td>
                    <td>{{ $j->previous_due_at ? Waktu::tanggal(Carbon::parse($j->previous_due_at)) : '-' }}</td><td>{{ Waktu::tanggal(Carbon::parse($j->proposed_due_at)) }}</td>
                    <td>{{ $j->reason }}</td><td>{{ $j->aktivitas ?? '-' }}</td><td><x-lencana :kode="$j->status" :teks="Kode::label(Kode::SCHEDULE_STATUS, $j->status)"/></td>
                    <td>{{ $j->pengaju }} / {{ $j->pemutus ?? '-' }}</td></tr>
            @empty<tr><td colspan="8" class="teks-2">Tidak ada perubahan jadwal di rentang ini.</td></tr>@endforelse</tbody>
        </table></div></div>
    <div class="kartu"><h2 class="kartu-judul">Aktivitas di rentang ini</h2>
        <div class="table-responsive"><table class="table align-middle small">
            <thead><tr><th scope="col">Tanggal</th><th scope="col">Project</th><th scope="col">Aktivitas</th><th scope="col">Peserta</th><th scope="col" class="angka">Durasi</th><th scope="col">Dampak</th></tr></thead>
            <tbody>@forelse ($aktivitas as $a)
                <tr><td>{{ Waktu::tanggal($a->occurred_at ?? $a->scheduled_at ?? $a->created_at) }}</td><td>{{ $a->project?->name }}</td><td>{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</td>
                    <td>{{ $a->participants->pluck('name')->implode(', ') ?: '-' }}</td><td class="angka">{{ $a->duration_minutes !== null ? $a->duration_minutes.' mnt' : '-' }}</td><td>{{ Kode::label(Kode::IMPACT_TYPE, $a->impact_type) }}</td></tr>
            @empty<tr><td colspan="6" class="teks-2">Tidak ada aktivitas.</td></tr>@endforelse</tbody>
        </table></div></div>
</x-layouts.admin>
