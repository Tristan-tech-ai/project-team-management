@php use App\Support\Codes; use App\Support\Dates; use Illuminate\Support\Carbon; @endphp
<x-layouts.admin title="Laporan Operasional">
    <div class="page-header"><div><div class="crumb">Management</div><h1>Laporan Operasional</h1><p>Periode, export, dan jejak perubahan jadwal dengan bukti aktivitas, peserta, dampak, dan keputusan.</p></div></div>
    <form method="get" class="panel mb-3"><div class="row g-2 align-items-end">
        <div class="col-6 col-md-3"><label class="form-label" for="r-d">Dari</label><input id="r-d" type="date" name="from" class="form-control" value="{{ $from->toDateString() }}"></div>
        <div class="col-6 col-md-3"><label class="form-label" for="r-s">Sampai</label><input id="r-s" type="date" name="until" class="form-control" value="{{ $until->toDateString() }}"></div>
        <div class="col-12 col-md-auto"><button class="btn btn-outline-secondary" type="submit">Tampilkan</button></div>
        @if($canExport)
            <div class="col-12 col-md-auto ms-md-auto d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary" href="{{ route('management.export', ['kind' => 'schedule', 'from' => $from->toDateString(), 'until' => $until->toDateString()]) }}">Export jejak jadwal</a>
                <a class="btn btn-outline-primary" href="{{ route('management.export', ['kind' => 'monitoring']) }}">Export monitoring</a>
                <a class="btn btn-outline-primary" href="{{ route('management.export', ['kind' => 'members']) }}">Export beban anggota</a>
            </div>
        @endif
    </div></form>
    <div class="panel mb-3"><h2 class="panel-title">Jejak perubahan jadwal</h2>
        <div class="table-responsive"><table class="table align-middle small">
            <thead><tr><th scope="col">Project / task</th><th scope="col">Target awal</th><th scope="col">Sebelum</th><th scope="col">Usulan</th><th scope="col">Alasan</th><th scope="col">Aktivitas</th><th scope="col">Status</th><th scope="col">Pengaju / pemutus</th></tr></thead>
            <tbody>@forelse($trail as $j)
                <tr><td><strong>{{ $j->project }}</strong><div>{{ $j->task ?? 'Deadline project' }}</div></td><td>{{ $j->baseline ? Dates::date(Carbon::parse($j->baseline)) : '-' }}</td>
                    <td>{{ $j->previous_due_at ? Dates::date(Carbon::parse($j->previous_due_at)) : '-' }}</td><td>{{ Dates::date(Carbon::parse($j->proposed_due_at)) }}</td>
                    <td>{{ $j->reason }}</td><td>{{ $j->activities ?? '-' }}</td><td><x-badge :code="$j->status" :text="Codes::label(Codes::SCHEDULE_STATUS, $j->status)"/></td>
                    <td>{{ $j->requester }} / {{ $j->decider ?? '-' }}</td></tr>
            @empty<tr><td colspan="8" class="muted">Tidak ada perubahan jadwal di rentang ini.</td></tr>@endforelse</tbody>
        </table></div></div>
    <div class="panel"><h2 class="panel-title">Aktivitas di rentang ini</h2>
        <div class="table-responsive"><table class="table align-middle small">
            <thead><tr><th scope="col">Tanggal</th><th scope="col">Project</th><th scope="col">Aktivitas</th><th scope="col">Peserta</th><th scope="col" class="numeric">Durasi</th><th scope="col">Dampak</th></tr></thead>
            <tbody>@forelse($activities as $a)
                <tr><td>{{ Dates::date($a->occurred_at ?? $a->scheduled_at ?? $a->created_at) }}</td><td>{{ $a->project?->name }}</td><td>{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</td>
                    <td>{{ $a->participants->pluck('name')->implode(', ') ?: '-' }}</td><td class="numeric">{{ $a->duration_minutes !== null ? $a->duration_minutes.' mnt' : '-' }}</td><td>{{ Codes::label(Codes::IMPACT_TYPE, $a->impact_type) }}</td></tr>
            @empty<tr><td colspan="6" class="muted">Tidak ada aktivitas.</td></tr>@endforelse</tbody>
        </table></div></div>
</x-layouts.admin>
