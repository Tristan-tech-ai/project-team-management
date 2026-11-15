@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.team title="Beranda">
    <div class="page-header"><div><h1 class="mb-0">Halo, {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</h1><p>{{ Dates::date(now()) }}</p></div></div>
    <div class="status-summary mb-3">
        @php($look = ['backlog' => ['list-todo', ''], 'in_progress' => ['timer', ''], 'review' => ['clipboard-check', 'is-review'], 'done' => ['circle-check', 'is-done']])
        @foreach(Codes::TASK_STATUS as $k => $v)
            <a class="panel stat-card stat-link {{ $look[$k][1] ?? '' }}" href="{{ route('tasks.index', ['status' => $k]) }}">
                <span class="stat-label"><span class="stat-icon"><x-icon :name="$look[$k][0] ?? 'circle-dot'"/></span>{{ $v }}</span><span class="number-large">{{ $counts[$k] ?? 0 }}</span></a>
        @endforeach
    </div>
    @if($revision->isNotEmpty())
        <div class="alert alert-warning" role="status"><strong>Perlu revisi:</strong>
            @foreach($revision as $r)<a href="{{ route('tasks.show', $r->task_id) }}">{{ $r->task->title }}</a>@if(! $loop->last), @endif @endforeach</div>
    @endif
    @if($pendingForMe)
        <a class="panel d-flex align-items-center justify-content-between mb-3 text-decoration-none text-reset" href="{{ route('review.index') }}">
            <span><x-icon name="clipboard-check"/> <strong class="numeric">{{ $pendingForMe }}</strong> hasil menunggu review-mu</span><x-icon name="chevron-right"/></a>
    @endif
    <section class="mb-3" aria-labelledby="h-today">
        <h2 class="mb-2" id="h-today">Deadline hari ini</h2>
        <div class="task-list">@forelse($today as $t) @include('team._card', ['t' => $t]) @empty<p class="small muted mb-0">Tidak ada deadline hari ini.</p>@endforelse</div>
    </section>
    @if($overdue->isNotEmpty())
        <section class="mb-3" aria-labelledby="h-late"><h2 class="mb-2 text-danger" id="h-late">Terlambat</h2>
            <div class="task-list">@foreach($overdue as $t) @include('team._card', ['t' => $t]) @endforeach</div></section>
    @endif
    @if($inReview->isNotEmpty())
        <section class="mb-3" aria-labelledby="h-review"><h2 class="mb-2" id="h-review">Sedang direview</h2>
            <div class="task-list">@foreach($inReview as $t) @include('team._card', ['t' => $t]) @endforeach</div></section>
    @endif
    <section aria-labelledby="h-project">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="mb-0" id="h-project">Project saya</h2><a class="small" href="{{ route('projects.index') }}">Semua project</a></div>
        <div class="task-list">@forelse($projects as $p)
            <a class="panel text-decoration-none text-reset" href="{{ route('projects.board', $p) }}"><div class="fw-semibold">{{ $p->name }}</div><div class="small muted">{{ Codes::label(Codes::PROJECT_TYPE, $p->project_type) }} · Buka board</div></a>
        @empty<p class="small muted mb-0">Kamu belum menjadi anggota project.</p>@endforelse</div>
        <a class="btn btn-outline-primary mt-3" href="{{ route('activities.index') }}"><x-icon name="activity"/> Aktivitas saya</a>
    </section>
</x-layouts.team>
