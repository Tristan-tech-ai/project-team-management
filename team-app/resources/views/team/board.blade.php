<x-layouts.team :title="'Board '.$project->name">
    @php($project->loadMissing(['pic:id,name', 'periods']))
    @include('team._header')
    @if($project->periods->count() > 1)
        <form method="get" class="mb-3"><label class="form-label small" for="period">Periode</label>
            <select id="period" name="period" class="form-select" onchange="this.form.requestSubmit()">@foreach($project->periods->sortByDesc('starts_at') as $p)<option value="{{ $p->id }}" @selected($selectedPeriod === $p->id)>{{ $p->name }}</option>@endforeach</select></form>
    @endif
    <p class="small muted d-lg-none">Ubah status lewat pilihan di tiap kartu. Review dan Selesai lewat detail task.</p>
    @include('shared.board', ['columns' => $columns])
</x-layouts.team>
