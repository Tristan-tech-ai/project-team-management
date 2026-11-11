@php use App\Support\Kode; use App\Support\Waktu; @endphp
<x-layouts.tim judul="Aktivitas Saya">
    <div class="kepala-halaman"><div><h1 class="mb-0">Aktivitas Saya</h1><p>Rapat, kunjungan, revisi, dan hambatan yang kamu catat atau hadiri.</p></div></div>
    @if ($projects->isNotEmpty())
        <form method="get" class="kartu mb-3 d-flex flex-wrap gap-2 align-items-end" action="{{ route('projects.activities.create', $projects->first()) }}" onsubmit="this.action=this.dataset.dasar.replace('__ID__', this.project.value)" data-dasar="{{ route('projects.activities.create', ['project' => '__ID__']) }}">
            <div class="flex-grow-1"><label class="form-label small" for="a-p">Catat aktivitas di project</label>
                <select id="a-p" name="project" class="form-select">@foreach ($projects as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
            <button class="btn btn-primary" type="submit"><x-ikon name="plus"/> Tambah activity</button>
        </form>
    @endif
    <div class="kartu">
        <ul class="timeline">
            @forelse ($aktivitas as $a)
                <li class="{{ $a->impact_type === 'blocked' ? 'hambat' : ($a->impact_type !== 'none' ? 'dampak' : '') }}">
                    <div class="fw-semibold">{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                    <div class="small teks-2">{{ $a->project?->name }} · {{ Waktu::tanggalJam($a->occurred_at ?? $a->scheduled_at) }}@if($a->duration_minutes !== null) · {{ $a->duration_minutes }} menit @endif</div>
                    <div class="small">Peserta: {{ $a->participants->pluck('name')->implode(', ') ?: '-' }} · {{ Kode::label(Kode::IMPACT_TYPE, $a->impact_type) }}</div>
                </li>
            @empty
                <li class="small teks-2">Belum ada aktivitas. Catat rapat atau kunjungan supaya terlihat di laporan.</li>
            @endforelse
        </ul>
        {{ $aktivitas->links() }}
    </div>
</x-layouts.tim>
