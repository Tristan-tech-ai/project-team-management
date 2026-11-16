@php
    use App\Support\Codes; use App\Support\Dates;
    $hour = (int) Dates::local(now())->format('G');
    $greeting = $hour < 11 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat siang' : ($hour < 19 ? 'Selamat sore' : 'Selamat malam'));
    $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first();
    $stats = [
        ['Project aktif', $kpi['active_projects'], null, 'folder-kanban', '', route('projects.index')],
        ['Task berjalan', $kpi['running_tasks'], 'dari '.$kpi['main_tasks'].' task utama', 'list-todo', '', route('tasks.index', ['status' => 'in_progress'])],
        ['Terlambat', $kpi['overdue'], 'lewat deadline', 'triangle-alert', 'is-late', route('tasks.index', ['overdue' => 1])],
        ['Menunggu review', $kpi['review'], 'hasil yang dikirim', 'clipboard-check', 'is-review', auth()->user()->can('tasks.review') ? route('tasks.review') : null],
    ];
@endphp
<x-layouts.admin title="Dashboard">
    <div class="page-header"><div><div class="crumb">{{ Dates::date(now()) }}</div><h1>{{ $greeting }}, {{ $firstName }}</h1>
        <p>Kamu memegang <strong class="numeric">{{ $myTasks }}</strong> task yang belum selesai.</p></div>
        @can('create', \App\Models\Project::class)<a class="btn btn-primary" href="{{ route('projects.create') }}"><x-icon name="plus"/> Buat project</a>@endcan</div>
    @php($stepsDone = collect($firstSteps)->where(1, true)->count())
    @if($firstSteps && $stepsDone < count($firstSteps))
        {{-- I8: first steps on a new installation, ticked from the data; can be hidden for this browser. --}}
        <section class="panel first-steps mb-3" data-first-steps aria-labelledby="first-steps-title">
            <div class="panel-head"><h2 class="panel-title" id="first-steps-title">Langkah pertama <span class="muted fw-normal numeric">{{ $stepsDone }}/{{ count($firstSteps) }}</span></h2>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-first-steps-hide>Sembunyikan</button></div>
            <div class="meter mb-3"><span style="width:{{ round($stepsDone * 100 / count($firstSteps)) }}%"></span></div>
            <ol class="first-steps-list">
                @foreach($firstSteps as [$label, $done, $url])
                    <li class="{{ $done ? 'is-done' : '' }}"><x-icon :name="$done ? 'circle-check' : 'circle-dot'"/>
                        @if($done)<span>{{ $label }}</span><span class="visually-hidden">(selesai)</span>@else<a href="{{ $url }}">{{ $label }}</a>@endif</li>
                @endforeach
            </ol>
        </section>
    @endif
    <div class="row g-3 mb-3" data-refresh-region="stats">
        @foreach($stats as [$label, $value, $hint, $icon, $tone, $url])
            <div class="col-6 col-xl-3">
                @if($url)<a href="{{ $url }}" class="panel stat-card stat-link h-100 {{ $tone }}">@else<div class="panel stat-card h-100 {{ $tone }}">@endif
                    <span class="stat-label"><span class="stat-icon"><x-icon :name="$icon"/></span>{{ $label }}</span>
                    <span class="kpi-value">{{ $value }}</span>@if($hint)<span class="small muted">{{ $hint }}</span>@endif
                @if($url)</a>@else</div>@endif
            </div>
        @endforeach
    </div>
    <div class="row g-3 mb-3">
        <div class="col-xl-7">@include('shared.todo', ['todo' => $todo])</div>
        <div class="col-xl-5"><div class="panel h-100" data-refresh-region="overdue"><div class="panel-head"><h2 class="panel-title">Keterlambatan</h2>
                @if($overdue->isNotEmpty())<a class="small" href="{{ route('tasks.index', ['overdue' => 1]) }}">Lihat semua</a>@endif</div>
            @forelse($overdue as $t)
                <div class="list-row"><div class="min-w-0 flex-grow-1"><a class="fw-semibold" href="{{ route('tasks.show', $t) }}" data-task-panel>{{ $t->title }}</a>
                    <div class="small muted text-truncate">{{ $t->project?->name }} · PIC {{ $t->pic?->name }}</div></div>
                    <span class="pill pill-late text-nowrap">{{ Dates::relative($t->due_at) }}</span></div>
            @empty
                <div class="empty-state py-3"><span class="empty-icon"><x-icon name="circle-check" :size="22"/></span><strong>Tidak ada task terlambat.</strong></div>
            @endforelse</div></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-8"><div class="panel h-100"><div class="panel-head"><h2 class="panel-title">Project aktif</h2><a class="small" href="{{ route('projects.index') }}">Semua project</a></div>
            @include('admin.management._monitor', ['rows' => $active])</div></div>
        <div class="col-xl-4"><div class="panel h-100"><h2 class="panel-title">Aktivitas terakhir</h2>
            @forelse($activities as $a)
                <div class="list-row"><div class="min-w-0"><div class="fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                    <div class="small muted">{{ $a->project?->name }} · {{ $a->creator?->name }} · {{ Dates::relative($a->created_at, past: true) }}</div></div></div>
            @empty
                <div class="empty-state py-3"><span class="empty-icon"><x-icon name="activity" :size="22"/></span><strong>Belum ada aktivitas.</strong><span>Rapat, kunjungan, dan hambatan yang dicatat muncul di sini.</span></div>
            @endforelse</div></div>
    </div>
</x-layouts.admin>
