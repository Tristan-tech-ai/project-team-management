<x-layouts.tim :judul="'Board '.$project->name">
    @php($project->loadMissing(['pic:id,name', 'periods']))
    @include('tim._kepala')
    @if ($project->periods->count() > 1)
        <form method="get" class="mb-3"><label class="form-label small" for="periode">Periode</label>
            <select id="periode" name="periode" class="form-select" onchange="this.form.requestSubmit()">@foreach ($project->periods->sortByDesc('starts_at') as $p)<option value="{{ $p->id }}" @selected($periodeDipilih === $p->id)>{{ $p->name }}</option>@endforeach</select></form>
    @endif
    <p class="small teks-2 d-lg-none">Ubah status lewat pilihan di tiap kartu. Review dan Selesai lewat detail task.</p>
    @include('bersama.board', ['kolom' => $kolom])
</x-layouts.tim>
