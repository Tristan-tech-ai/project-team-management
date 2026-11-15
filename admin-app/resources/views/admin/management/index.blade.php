@php use App\Support\Codes; use App\Support\Dates; $pt = $kpi['per_type']; @endphp
<x-layouts.admin title="Ringkasan Manajemen">
    <div class="page-header"><div><div class="crumb">Manajemen</div><h1>Ringkasan manajemen</h1><p>Pantau prioritas dan aktivitas kerja lintas divisi.</p></div></div>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="panel h-100"><div class="muted">Project aktif</div><div class="kpi-value" style="color:var(--ptm-action)">{{ $kpi['active_projects'] }}</div>
            <div class="small muted">{{ $pt['one_time'] ?? 0 }} sekali jadi · {{ $pt['recurring'] ?? 0 }} berulang · {{ $pt['ongoing'] ?? 0 }} berkelanjutan</div></div></div>
        <div class="col-6 col-xl-3"><div class="panel h-100"><div class="muted">Task berjalan</div><div class="kpi-value" style="color:var(--ptm-action)">{{ $kpi['running_tasks'] }}</div><div class="small muted">dari {{ $kpi['main_tasks'] }} task utama</div></div></div>
        <div class="col-6 col-xl-3"><div class="panel h-100"><div class="muted">Terlambat</div><div class="kpi-value" style="color:var(--ptm-late)">{{ $kpi['overdue'] }}</div><div class="small muted">lewat deadline, belum selesai</div></div></div>
        <div class="col-6 col-xl-3"><div class="panel h-100"><div class="muted">Menunggu review</div><div class="kpi-value" style="color:var(--ptm-review)">{{ $kpi['review'] }}</div><div class="small muted">butuh keputusan PIC</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-8"><div class="panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="panel-title mb-0">Monitoring Project</h2><a class="small" href="{{ route('management.projects') }}">Lihat semua</a></div>
            @include('admin.management._monitor', ['rows' => $monitor])
        </div></div>
        <div class="col-xl-4"><div class="panel h-100">
            <h2 class="panel-title">Perlu perhatian</h2>
            <a class="d-block rounded-3 p-3 mb-2 text-decoration-none" style="background:var(--ptm-review-soft);color:var(--ptm-review)" href="{{ route('activities.impacts') }}"><strong class="numeric">{{ $kpi['pending_schedules'] }}</strong> perubahan jadwal<div class="small" style="color:var(--ptm-ink-2)">Menunggu persetujuan</div></a>
            <a class="d-block rounded-3 p-3 mb-2 text-decoration-none" style="background:var(--ptm-action-soft);color:var(--ptm-action-dark)" href="{{ route('activities.index') }}"><strong class="numeric">{{ $kpi['activities_7_days'] }}</strong> kegiatan 7 hari terakhir<div class="small" style="color:var(--ptm-ink-2)">Rapat, kunjungan, revisi, hambatan</div></a>
            <a class="d-block rounded-3 p-3 mb-2 text-decoration-none" style="background:var(--ptm-late-soft);color:var(--ptm-late)" href="{{ route('tasks.index', ['overdue' => 1]) }}"><strong class="numeric">{{ $kpi['overdue'] }}</strong> task terlambat<div class="small" style="color:var(--ptm-ink-2)">Lihat penyebab dan PIC</div></a>
            <a class="d-block rounded-3 p-3 mb-3 text-decoration-none" style="background:var(--ptm-done-soft);color:var(--ptm-done)" href="{{ route('tasks.review') }}"><strong class="numeric">{{ $kpi['review'] }}</strong> task review<div class="small" style="color:var(--ptm-ink-2)">Butuh hasil pemeriksaan</div></a>
            <p class="small muted mb-0"><span class="numeric">{{ $kpi['unique_members'] }}</span> anggota unik memegang <span class="numeric">{{ $kpi['assignments'] }}</span> penugasan task.</p>
        </div></div>
        <div class="col-lg-6"><div class="panel h-100"><h2 class="panel-title">Antrian review</h2>
            @forelse($review as $s)<div class="mb-2"><a href="{{ route('tasks.show', $s->task_id) }}">{{ $s->task?->title }}</a><div class="small muted">{{ $s->submitter?->name }} · {{ Dates::dateTime($s->submitted_at) }}</div></div>
            @empty<p class="small muted mb-0">Tidak ada yang menunggu.</p>@endforelse</div></div>
        <div class="col-lg-6"><div class="panel h-100"><h2 class="panel-title">Operasional terbaru</h2>
            @forelse($operations as $a)<div class="mb-2"><span class="fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</span><div class="small muted">{{ $a->project?->name }} · {{ Codes::label(Codes::IMPACT_TYPE, $a->impact_type) }} · {{ Dates::date($a->occurred_at ?? $a->scheduled_at) }}</div></div>
            @empty<p class="small muted mb-0">Belum ada aktivitas.</p>@endforelse</div></div>
    </div>
</x-layouts.admin>
