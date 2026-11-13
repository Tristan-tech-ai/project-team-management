@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.admin title="Ringkasan Sistem">
    <div class="page-header"><div><div class="crumb">Dashboard</div><h1>Ringkasan Sistem</h1><p>Halo, {{ auth()->user()->name }}. Kamu memegang {{ $myTasks }} task yang belum selesai.</p></div>
        @can('create', \App\Models\Project::class)<a class="btn btn-primary" href="{{ route('projects.create') }}"><x-icon name="plus"/> Buat project</a>@endcan</div>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><a class="panel d-block h-100 text-decoration-none text-reset" href="{{ route('projects.index') }}"><div class="muted">Project aktif</div><div class="kpi-value" style="color:var(--ptm-action)">{{ $kpi['active_projects'] }}</div></a></div>
        <div class="col-6 col-xl-3"><a class="panel d-block h-100 text-decoration-none text-reset" href="{{ route('tasks.index', ['status' => 'in_progress']) }}"><div class="muted">Task berjalan</div><div class="kpi-value" style="color:var(--ptm-action)">{{ $kpi['running_tasks'] }}</div><div class="small muted">dari {{ $kpi['main_tasks'] }} task utama</div></a></div>
        <div class="col-6 col-xl-3"><a class="panel d-block h-100 text-decoration-none text-reset" href="{{ route('tasks.index', ['overdue' => 1]) }}"><div class="muted">Terlambat</div><div class="kpi-value" style="color:var(--ptm-late)">{{ $kpi['overdue'] }}</div></a></div>
        <div class="col-6 col-xl-3"><div class="panel h-100"><div class="muted">Menunggu review</div><div class="kpi-value" style="color:var(--ptm-review)">{{ $kpi['review'] }}</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="panel h-100"><h2 class="panel-title">Project aktif</h2>
            @include('admin.management._monitor', ['rows' => $active])</div></div>
        <div class="col-xl-5"><div class="panel h-100"><h2 class="panel-title">Keterlambatan</h2>
            @forelse($overdue as $t)<div class="d-flex justify-content-between gap-2 mb-2"><div><a href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a><div class="small muted">{{ $t->project?->name }} · PIC {{ $t->pic?->name }}</div></div>
                <span class="small text-danger text-nowrap">{{ Dates::date($t->due_at) }}</span></div>
            @empty<p class="small muted mb-0">Tidak ada task terlambat.</p>@endforelse</div></div>
        @if($review->isNotEmpty())
            <div class="col-xl-6"><div class="panel h-100"><h2 class="panel-title">Review menunggu</h2>
                @foreach($review as $s)<div class="mb-2"><a href="{{ route('tasks.show', $s->task_id) }}">{{ $s->task?->title }}</a><div class="small muted">{{ $s->submitter?->name }} · {{ Dates::dateTime($s->submitted_at) }}</div></div>@endforeach
                <a class="small" href="{{ route('tasks.review') }}">Buka antrian review</a></div></div>
        @endif
        <div class="col-xl-6"><div class="panel h-100"><h2 class="panel-title">Aktivitas terakhir</h2>
            @forelse($activities as $a)<div class="mb-2"><span class="fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</span><div class="small muted">{{ $a->project?->name }} · {{ $a->creator?->name }} · {{ Dates::dateTime($a->created_at) }}</div></div>
            @empty<p class="small muted mb-0">Belum ada aktivitas.</p>@endforelse</div></div>
    </div>
</x-layouts.admin>
