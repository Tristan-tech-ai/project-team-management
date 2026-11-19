<x-layouts.admin title="Monitoring Divisi & Anggota">
    <div class="page-header"><div><div class="crumb">Manajemen</div><h1>Monitoring Divisi &amp; Anggota</h1><p>Beban kerja, keterlibatan, dan durasi yang tercatat. Anggota unik dihitung sekali walau ada di beberapa divisi.</p></div>
        @if(auth()->user()->hasPermission('management.export'))<a class="btn btn-outline-primary" href="{{ route('management.export', ['kind' => 'members']) }}"><x-icon name="external-link"/> Unduh CSV</a>@endif</div>
    <div class="panel mb-3"><h2 class="panel-title">Per divisi</h2>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th scope="col">Divisi</th><th scope="col">Company</th><th scope="col" class="numeric">Anggota unik</th><th scope="col" class="numeric">Project terlibat</th><th scope="col" class="numeric">Task aktif anggota</th></tr></thead>
            <tbody>@forelse($divisions as $d)<tr><td><a href="{{ route('management.teams', ['division_id' => $d->id]) }}">{{ $d->name }}</a></td><td>{{ $d->company }}</td><td class="numeric">{{ $d->members }}</td><td class="numeric">{{ $d->project }}</td><td class="numeric">{{ $d->active_tasks }}</td></tr>
            @empty<tr><td colspan="5" class="muted">Belum ada divisi.</td></tr>@endforelse</tbody>
        </table></div></div>
    <div class="panel">
        <form method="get" class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2" data-auto-submit>
            <div><h2 class="panel-title mb-0">KPI per anggota</h2><p class="small muted mb-0">Tepat waktu: task selesai sebelum deadline yang disetujui. Rutin: hari yang dicentang dari hari rutin yang sudah lewat.</p></div>
            <div class="d-flex gap-2 align-items-end"><div><label class="form-label" for="dv">Divisi</label><select id="dv" name="division_id" class="form-select"><option value="">Semua</option>@foreach($divisionOptions as $k => $v)<option value="{{ $k }}" @selected($selectedDivision == $k)>{{ $v }}</option>@endforeach</select></div>
                <button class="btn btn-outline-secondary no-js-only" type="submit">Saring</button></div>
        </form>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th scope="col">Anggota</th><th scope="col" class="numeric">Project</th><th scope="col" class="numeric">Task aktif</th><th scope="col" class="numeric">Terlambat</th><th scope="col" class="numeric">Selesai</th><th scope="col" class="numeric">Tepat waktu</th><th scope="col" class="numeric">Rutin harian</th><th scope="col" class="numeric">Jam kerja tercatat</th><th scope="col" class="numeric">Jam aktivitas</th></tr></thead>
            <tbody>@forelse($people as $o)<tr><td>{{ $o->name }}</td><td class="numeric">{{ $o->project }}</td><td class="numeric">{{ $o->active }}</td><td class="numeric {{ $o->overdue ? 'text-danger fw-semibold' : '' }}">{{ $o->overdue }}</td>
                <td class="numeric">{{ $o->done }}</td>
                <td class="numeric">@if($o->done){{ round($o->on_time * 100 / $o->done) }}% <span class="muted small">({{ $o->on_time }}/{{ $o->done }})</span>@else<span class="muted">-</span>@endif</td>
                <td class="numeric">@if($o->routine_due){{ $o->routine_done }}/{{ $o->routine_due }} hari @else<span class="muted">-</span>@endif</td>
                <td class="numeric">{{ number_format($o->work_minutes / 60, 1, ',', '.') }}</td><td class="numeric">{{ number_format($o->activity_minutes / 60, 1, ',', '.') }}</td></tr>
            @empty<tr><td colspan="9" class="muted">Belum ada anggota project.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</x-layouts.admin>
