@php use App\Support\Kode; use App\Support\Waktu; @endphp
<x-layouts.admin :judul="$project->name">
    @include('admin.projects._kepala')
    @if ($project->periods->isNotEmpty())
        <form class="d-flex flex-wrap align-items-end gap-2 mb-3" method="get">
            <div><label class="form-label" for="periode">Periode</label>
                <select id="periode" name="periode" class="form-select" onchange="this.form.requestSubmit()"><option value="">Semua periode</option>
                    @foreach ($project->periods as $p)<option value="{{ $p->id }}" @selected($periodeDipilih === $p->id)>{{ $p->name }} ({{ Kode::label(Kode::PERIOD_STATUS, $p->status) }})</option>@endforeach</select></div>
            @if ($bolehUbah)<button class="btn btn-outline-primary" type="submit" form="buka-periode"><x-ikon name="calendar-clock"/> Buka periode baru</button>@endif
        </form>
        @if ($bolehUbah)<form id="buka-periode" method="post" action="{{ route('projects.periods.store', $project) }}" data-konfirmasi="Buka periode baru? Periode berjalan ditutup, project induk tetap berjalan, dan task dari template dibuat untuk periode baru.">@csrf</form>@endif
    @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="kartu mb-3">
                <div class="d-flex justify-content-between align-items-end mb-2"><h2 class="kartu-judul mb-0">Progress</h2><span class="kpi-nilai">{{ $progres['persen'] }}%</span></div>
                <div class="progres mb-2" style="height:12px"><span style="width:{{ $progres['persen'] }}%"></span></div>
                <p class="small teks-2 mb-3">{{ $progres['selesai'] }} dari {{ $progres['total'] }} task utama selesai. Subtask tidak dihitung di sini supaya tidak ganda.</p>
                <div class="row g-2 text-center">
                    @foreach (Kode::TASK_STATUS as $k => $v)
                        <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="small teks-2">{{ $v }}</div><div class="fs-4 fw-bold angka">{{ $perStatus[$k] ?? 0 }}</div></div></div>
                    @endforeach
                </div>
            </div>
            <div class="kartu mb-3">
                <h2 class="kartu-judul">Timeline</h2>
                <dl class="row small mb-0">
                    <dt class="col-sm-4 teks-2 fw-normal">Mulai</dt><dd class="col-sm-8">{{ Waktu::tanggal($project->start_at) }}</dd>
                    <dt class="col-sm-4 teks-2 fw-normal">Target awal</dt><dd class="col-sm-8">{{ Waktu::tanggal($project->baseline_due_at) }}</dd>
                    <dt class="col-sm-4 teks-2 fw-normal">Deadline terbaru</dt><dd class="col-sm-8">{{ Waktu::tanggal($project->due_at) }}@if($project->baseline_due_at && $project->due_at && ! $project->baseline_due_at->equalTo($project->due_at)) <span class="lencana lencana-risk">bergeser {{ (int) round($project->baseline_due_at->diffInDays($project->due_at, false)) }} hari dari target awal</span>@endif</dd>
                    <dt class="col-sm-4 teks-2 fw-normal">Estimasi task utama</dt><dd class="col-sm-8 angka">{{ number_format($estimasi / 60, 1, ',', '.') }} jam</dd>
                    <dt class="col-sm-4 teks-2 fw-normal">Waktu kerja tercatat</dt><dd class="col-sm-8 angka">{{ number_format($menitKerja / 60, 1, ',', '.') }} jam</dd>
                    <dt class="col-sm-4 teks-2 fw-normal">Template</dt><dd class="col-sm-8">{{ $project->template?->name ?? '-' }}</dd>
                    <dt class="col-sm-4 teks-2 fw-normal">Penugasan</dt><dd class="col-sm-8 angka">{{ $hitung['penugasan'] }} penugasan untuk {{ $hitung['orang'] }} orang unik</dd>
                </dl>
            </div>
            @if ($project->description)<div class="kartu mb-3"><h2 class="kartu-judul">Deskripsi</h2><p class="mb-0" style="white-space:pre-line">{{ $project->description }}</p></div>@endif
        </div>
        <div class="col-lg-4">
            <div class="kartu mb-3">
                <h2 class="kartu-judul">Task terlambat</h2>
                @forelse ($terlambat as $t)
                    <div class="mb-2"><a href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a><div class="small text-danger">Deadline {{ Waktu::tanggal($t->due_at) }}</div></div>
                @empty
                    <p class="small teks-2 mb-0">Tidak ada task yang lewat deadline.</p>
                @endforelse
            </div>
            <div class="kartu">
                <h2 class="kartu-judul">Catatan operasional terakhir</h2>
                <ul class="timeline">@forelse ($timeline as $a)
                    <li class="{{ $a->impact_type === 'blocked' ? 'hambat' : ($a->impact_type !== 'none' ? 'dampak' : '') }}"><div class="small fw-semibold">{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                        <div class="small teks-2">{{ Waktu::tanggalJam($a->occurred_at ?? $a->scheduled_at ?? $a->created_at) }} · {{ Kode::label(Kode::IMPACT_TYPE, $a->impact_type) }}</div></li>
                    @empty<li class="small teks-2">Belum ada aktivitas.</li>@endforelse</ul>
            </div>
        </div>
    </div>
</x-layouts.admin>
