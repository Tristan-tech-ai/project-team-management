@php use App\Support\Codes; use App\Support\Dates; use App\Services\ManagementMetricsService as M; @endphp
<x-layouts.team :title="$project->name">
    @include('team._header')
    <div class="row g-3">
        <div class="col-lg-6"><div class="panel h-100">
            <div class="d-flex justify-content-between align-items-end"><h2 class="panel-title mb-0">Progress</h2><span class="kpi-value">{{ $progress['percent'] }}%</span></div>
            <div class="meter my-2" style="height:12px"><span style="width:{{ $progress['percent'] }}%"></span></div>
            <p class="small muted">{{ $progress['done'] }} dari {{ $progress['total'] }} task utama selesai.</p>
            <dl class="row small mb-0">
                <dt class="col-5 muted fw-normal">Kondisi</dt><dd class="col-7"><x-badge :code="M::CONDITION_CLASSES[$condition] ?? 'neutral'" :text="M::CONDITIONS[$condition] ?? $condition"/></dd>
                <dt class="col-5 muted fw-normal">Target awal</dt><dd class="col-7">{{ Dates::date($project->baseline_due_at) }}</dd>
                <dt class="col-5 muted fw-normal">Deadline terbaru</dt><dd class="col-7">{{ Dates::date($project->due_at) }}</dd>
                <dt class="col-5 muted fw-normal">Client</dt><dd class="col-7">{{ $project->client?->name ?? 'Internal' }}</dd>
            </dl>
        </div></div>
        <div class="col-lg-6"><div class="panel h-100"><h2 class="panel-title">Tim</h2><p class="mb-0">{{ $team->implode(', ') }}</p>
            @if($project->description)<h2 class="panel-title mt-3">Catatan project</h2><div class="rich-text small">{!! \App\Support\RichText::toHtml($project->description) !!}</div>@endif</div></div>
        <div class="col-12"><div class="panel"><h2 class="panel-title">Catatan operasional</h2>
            <ul class="timeline">@forelse($notes as $a)
                <li class="{{ $a->impact_type === 'blocked' ? 'blocked' : ($a->impact_type !== 'none' ? 'impact' : '') }}"><div class="small fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                    <div class="small muted">{{ Dates::dateTime($a->occurred_at ?? $a->scheduled_at ?? $a->created_at) }} · {{ $a->creator?->name }} · {{ Codes::label(Codes::IMPACT_TYPE, $a->impact_type) }}</div></li>
            @empty<li class="small muted">Belum ada aktivitas.</li>@endforelse</ul></div></div>
    </div>
</x-layouts.team>
