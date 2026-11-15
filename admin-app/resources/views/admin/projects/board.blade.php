@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.admin :title="'Board '.$project->name">
    @include('admin.projects._header')
    <form class="d-flex flex-wrap align-items-end gap-2 mb-3" method="get" data-auto-submit>
        @if($project->periods->isNotEmpty())
            <div><label class="form-label" for="b-period">Periode</label>
                <select id="b-period" name="period" class="form-select">@foreach($project->periods->sortByDesc('starts_at') as $p)<option value="{{ $p->id }}" @selected($selectedPeriod === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        @endif
        <div><label class="form-label" for="b-pic">PIC</label><select id="b-pic" name="pic" class="form-select"><option value="">Semua</option>@foreach($memberOptions as $id => $n)<option value="{{ $id }}" @selected(request('pic') == $id)>{{ $n }}</option>@endforeach</select></div>
        <div><label class="form-label" for="b-members">Anggota</label><select id="b-members" name="members" class="form-select"><option value="">Semua</option>@foreach($memberOptions as $id => $n)<option value="{{ $id }}" @selected(request('members') == $id)>{{ $n }}</option>@endforeach</select></div>
        <button class="btn btn-outline-secondary no-js-only" type="submit"><x-icon name="funnel"/> Saring</button>
    </form>
    <p class="small muted d-none d-lg-block">Seret kartu untuk mengubah status. Review lewat Kirim hasil di task, Selesai lewat persetujuan PIC atau manager.</p>
    @include('shared.board', ['columns' => $columns])
    @if($notes || $pendingSchedules)
        <div class="alert alert-warning mt-3 mb-0" role="status">
            <strong>Catatan operasional:</strong>
            @if($notes){{ Codes::label(Codes::ACTIVITY_TYPE, $notes->activity_type) }} {{ $notes->title }} {{ Dates::date($notes->occurred_at ?? $notes->scheduled_at) }}@if($notes->duration_minutes) ({{ $notes->duration_minutes }} menit)@endif · {{ Codes::label(Codes::IMPACT_TYPE, $notes->impact_type) }}@endif
            @if($pendingSchedules) · <a href="{{ route('projects.activities', $project) }}">{{ $pendingSchedules }} perubahan jadwal menunggu persetujuan</a>@endif
        </div>
    @endif
</x-layouts.admin>
