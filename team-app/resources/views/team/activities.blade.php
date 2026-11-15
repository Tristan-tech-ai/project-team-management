@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.team title="Aktivitas Saya">
    <div class="page-header"><div><h1 class="mb-0">Aktivitas Saya</h1><p>Rapat, kunjungan, revisi, dan hambatan yang kamu catat atau hadiri.</p></div></div>
    @if($projects->isNotEmpty())
        {{-- One tap to the form of the right project (U9): buttons for a few projects, a picker for many. --}}
        <section class="panel mb-3" aria-labelledby="h-add-activity">
            <h2 class="panel-title" id="h-add-activity">Catat rapat, kunjungan, atau hambatan</h2>
            @if($projects->count() <= 6)
                <div class="d-flex flex-wrap gap-2">
                    @foreach($projects as $p)<a class="btn btn-outline-primary btn-truncate" href="{{ route('projects.activities.create', $p) }}" title="{{ $p->name }}"><x-icon name="plus" :size="16"/><span>{{ $p->name }}</span></a>@endforeach
                </div>
            @else
                <form method="get" class="d-flex flex-wrap gap-2 align-items-end" action="{{ route('projects.activities.create', $projects->first()) }}" onsubmit="this.action=this.dataset.urlTemplate.replace('__ID__', this.project.value)" data-url-template="{{ route('projects.activities.create', ['project' => '__ID__']) }}">
                    <div class="flex-grow-1"><label class="visually-hidden" for="a-p">Project</label>
                        <select id="a-p" name="project" class="form-select">@foreach($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <button class="btn btn-primary" type="submit"><x-icon name="plus"/> Catat aktivitas</button>
                </form>
            @endif
        </section>
    @endif
    <div class="panel">
        <ul class="timeline">
            @forelse($activities as $a)
                <li class="{{ $a->impact_type === 'blocked' ? 'blocked' : ($a->impact_type !== 'none' ? 'impact' : '') }}">
                    <div class="fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                    <div class="small muted">{{ $a->project?->name }} · {{ Dates::dateTime($a->occurred_at ?? $a->scheduled_at) }}@if($a->duration_minutes !== null) · {{ $a->duration_minutes }} menit @endif</div>
                    <div class="small">Peserta: {{ $a->participants->pluck('name')->implode(', ') ?: '-' }} · {{ Codes::label(Codes::IMPACT_TYPE, $a->impact_type) }}</div>
                </li>
            @empty
                <li class="small muted">Belum ada aktivitas. Catat rapat atau kunjungan supaya terlihat di laporan.</li>
            @endforelse
        </ul>
        {{ $activities->links() }}
    </div>
</x-layouts.team>
