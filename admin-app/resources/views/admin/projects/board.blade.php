@php use App\Support\Kode; use App\Support\Waktu; @endphp
<x-layouts.admin :judul="'Board '.$project->name">
    @include('admin.projects._kepala')
    <form class="d-flex flex-wrap align-items-end gap-2 mb-3" method="get">
        @if ($project->periods->isNotEmpty())
            <div><label class="form-label" for="b-periode">Periode</label>
                <select id="b-periode" name="periode" class="form-select">@foreach ($project->periods->sortByDesc('starts_at') as $p)<option value="{{ $p->id }}" @selected($periodeDipilih === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
        @endif
        <div><label class="form-label" for="b-pic">PIC</label><select id="b-pic" name="pic" class="form-select"><option value="">Semua</option>@foreach ($anggotaPilihan as $id => $n)<option value="{{ $id }}" @selected(request('pic') == $id)>{{ $n }}</option>@endforeach</select></div>
        <div><label class="form-label" for="b-anggota">Anggota</label><select id="b-anggota" name="anggota" class="form-select"><option value="">Semua</option>@foreach ($anggotaPilihan as $id => $n)<option value="{{ $id }}" @selected(request('anggota') == $id)>{{ $n }}</option>@endforeach</select></div>
        <button class="btn btn-outline-secondary" type="submit"><x-ikon name="funnel"/> Saring</button>
    </form>
    <p class="small teks-2 d-none d-lg-block">Seret kartu lewat pegangan di kiri judul. Pindah ke Review dilakukan dengan Kirim untuk Review di detail task; Selesai hanya lewat persetujuan PIC atau manager.</p>
    @include('bersama.board', ['kolom' => $kolom])
    @if ($catatan || $jadwalMenunggu)
        <div class="alert alert-warning mt-3 mb-0" role="status">
            <strong>Catatan operasional:</strong>
            @if ($catatan){{ Kode::label(Kode::ACTIVITY_TYPE, $catatan->activity_type) }} {{ $catatan->title }} {{ Waktu::tanggal($catatan->occurred_at ?? $catatan->scheduled_at) }}@if($catatan->duration_minutes) ({{ $catatan->duration_minutes }} menit)@endif · {{ Kode::label(Kode::IMPACT_TYPE, $catatan->impact_type) }}@endif
            @if ($jadwalMenunggu) · <a href="{{ route('projects.activities', $project) }}">{{ $jadwalMenunggu }} perubahan jadwal menunggu persetujuan</a>@endif
        </div>
    @endif
</x-layouts.admin>
