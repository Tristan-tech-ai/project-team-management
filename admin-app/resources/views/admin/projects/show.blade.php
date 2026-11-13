@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.admin :title="$project->name">
    @include('admin.projects._header')
    @if($project->periods->isNotEmpty())
        <form class="d-flex flex-wrap align-items-end gap-2 mb-3" method="get">
            <div><label class="form-label" for="period">Periode</label>
                <select id="period" name="period" class="form-select" onchange="this.form.requestSubmit()"><option value="">Semua periode</option>
                    @foreach($project->periods as $p)<option value="{{ $p->id }}" @selected($selectedPeriod === $p->id)>{{ $p->name }} ({{ Codes::label(Codes::PERIOD_STATUS, $p->status) }})</option>@endforeach</select></div>
            @if($canEdit)<button class="btn btn-outline-primary" type="submit" form="open-period"><x-icon name="calendar-clock"/> Buka periode baru</button>@endif
        </form>
        @if($canEdit)<form id="open-period" method="post" action="{{ route('projects.periods.store', $project) }}" data-confirm="Buka periode baru? Periode berjalan ditutup, project induk tetap berjalan, dan task dari template dibuat untuk periode baru.">@csrf</form>@endif
    @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="panel mb-3">
                <div class="d-flex justify-content-between align-items-end mb-2"><h2 class="panel-title mb-0">Progress</h2><span class="kpi-value">{{ $progress['percent'] }}%</span></div>
                <div class="meter mb-2" style="height:12px"><span style="width:{{ $progress['percent'] }}%"></span></div>
                <p class="small muted mb-3">{{ $progress['done'] }} dari {{ $progress['total'] }} task utama selesai. Subtask tidak dihitung di sini supaya tidak ganda.</p>
                <div class="row g-2 text-center">
                    @foreach(Codes::TASK_STATUS as $k => $v)
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="small muted">{{ $v }}</div><div class="fs-4 fw-bold numeric">{{ $perStatus[$k] ?? 0 }}</div></div></div>
                    @endforeach
                </div>
            </div>
            <div class="panel mb-3">
                <h2 class="panel-title">Timeline</h2>
                <dl class="row small mb-0">
                    <dt class="col-sm-4 muted fw-normal">Mulai</dt><dd class="col-sm-8">{{ Dates::date($project->start_at) }}</dd>
                    <dt class="col-sm-4 muted fw-normal">Target awal</dt><dd class="col-sm-8">{{ Dates::date($project->baseline_due_at) }}</dd>
                    <dt class="col-sm-4 muted fw-normal">Deadline terbaru</dt><dd class="col-sm-8">{{ Dates::date($project->due_at) }}@if($project->baseline_due_at && $project->due_at && ! $project->baseline_due_at->equalTo($project->due_at)) <span class="pill pill-risk">bergeser {{ (int) round($project->baseline_due_at->diffInDays($project->due_at, false)) }} hari dari target awal</span>@endif</dd>
                    <dt class="col-sm-4 muted fw-normal">Estimasi task utama</dt><dd class="col-sm-8 numeric">{{ number_format($estimate / 60, 1, ',', '.') }} jam</dd>
                    <dt class="col-sm-4 muted fw-normal">Waktu kerja tercatat</dt><dd class="col-sm-8 numeric">{{ number_format($workMinutes / 60, 1, ',', '.') }} jam</dd>
                    <dt class="col-sm-4 muted fw-normal">Template</dt><dd class="col-sm-8">{{ $project->template?->name ?? '-' }}</dd>
                    <dt class="col-sm-4 muted fw-normal">Penugasan</dt><dd class="col-sm-8 numeric">{{ $counts['assignments'] }} penugasan untuk {{ $counts['people'] }} orang unik</dd>
                </dl>
            </div>
            @if($project->description)<div class="panel mb-3"><h2 class="panel-title">Deskripsi</h2><p class="mb-0" style="white-space:pre-line">{{ $project->description }}</p></div>@endif
        </div>
        <div class="col-lg-4">
            <div class="panel mb-3">
                <h2 class="panel-title">Task terlambat</h2>
                @forelse($overdue as $t)
                    <div class="mb-2"><a href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a><div class="small text-danger">Deadline {{ Dates::date($t->due_at) }}</div></div>
                @empty
                    <p class="small muted mb-0">Tidak ada task yang lewat deadline.</p>
                @endforelse
            </div>
            <div class="panel">
                <h2 class="panel-title">Catatan operasional terakhir</h2>
                <ul class="timeline">@forelse($timeline as $a)
                    <li class="{{ $a->impact_type === 'blocked' ? 'blocked' : ($a->impact_type !== 'none' ? 'impact' : '') }}"><div class="small fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                        <div class="small muted">{{ Dates::dateTime($a->occurred_at ?? $a->scheduled_at ?? $a->created_at) }} · {{ Codes::label(Codes::IMPACT_TYPE, $a->impact_type) }}</div></li>
                    @empty<li class="small muted">Belum ada aktivitas.</li>@endforelse</ul>
            </div>
        </div>
    </div>
</x-layouts.admin>
