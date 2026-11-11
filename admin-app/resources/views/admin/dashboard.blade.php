@php use App\Support\Kode; use App\Support\Waktu; @endphp
<x-layouts.admin judul="Ringkasan Sistem">
    <div class="kepala-halaman"><div><div class="remah">Dashboard</div><h1>Ringkasan Sistem</h1><p>Halo, {{ auth()->user()->name }}. Kamu memegang {{ $tugasSaya }} task yang belum selesai.</p></div>
        @can('create', \App\Models\Project::class)<a class="btn btn-primary" href="{{ route('projects.create') }}"><x-ikon name="plus"/> Buat project</a>@endcan</div>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><a class="kartu d-block h-100 text-decoration-none text-reset" href="{{ route('projects.index') }}"><div class="teks-2">Project aktif</div><div class="kpi-nilai" style="color:var(--ptm-aksi)">{{ $kpi['project_aktif'] }}</div></a></div>
        <div class="col-6 col-xl-3"><a class="kartu d-block h-100 text-decoration-none text-reset" href="{{ route('tasks.index', ['status' => 'in_progress']) }}"><div class="teks-2">Task berjalan</div><div class="kpi-nilai" style="color:var(--ptm-aksi)">{{ $kpi['task_berjalan'] }}</div><div class="small teks-2">dari {{ $kpi['task_utama'] }} task utama</div></a></div>
        <div class="col-6 col-xl-3"><a class="kartu d-block h-100 text-decoration-none text-reset" href="{{ route('tasks.index', ['terlambat' => 1]) }}"><div class="teks-2">Terlambat</div><div class="kpi-nilai" style="color:var(--ptm-telat)">{{ $kpi['terlambat'] }}</div></a></div>
        <div class="col-6 col-xl-3"><div class="kartu h-100"><div class="teks-2">Menunggu review</div><div class="kpi-nilai" style="color:var(--ptm-review)">{{ $kpi['review'] }}</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-7"><div class="kartu h-100"><h2 class="kartu-judul">Project aktif</h2>
            @include('admin.management._monitor', ['baris' => $aktif])</div></div>
        <div class="col-xl-5"><div class="kartu h-100"><h2 class="kartu-judul">Keterlambatan</h2>
            @forelse ($terlambat as $t)<div class="d-flex justify-content-between gap-2 mb-2"><div><a href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a><div class="small teks-2">{{ $t->project?->name }} · PIC {{ $t->pic?->name }}</div></div>
                <span class="small text-danger text-nowrap">{{ Waktu::tanggal($t->due_at) }}</span></div>
            @empty<p class="small teks-2 mb-0">Tidak ada task terlambat.</p>@endforelse</div></div>
        @if ($review->isNotEmpty())
            <div class="col-xl-6"><div class="kartu h-100"><h2 class="kartu-judul">Review menunggu</h2>
                @foreach ($review as $s)<div class="mb-2"><a href="{{ route('tasks.show', $s->task_id) }}">{{ $s->task?->title }}</a><div class="small teks-2">{{ $s->submitter?->name }} · {{ Waktu::tanggalJam($s->submitted_at) }}</div></div>@endforeach
                <a class="small" href="{{ route('tasks.review') }}">Buka antrian review</a></div></div>
        @endif
        <div class="col-xl-6"><div class="kartu h-100"><h2 class="kartu-judul">Aktivitas terakhir</h2>
            @forelse ($aktivitas as $a)<div class="mb-2"><span class="fw-semibold">{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</span><div class="small teks-2">{{ $a->project?->name }} · {{ $a->creator?->name }} · {{ Waktu::tanggalJam($a->created_at) }}</div></div>
            @empty<p class="small teks-2 mb-0">Belum ada aktivitas.</p>@endforelse</div></div>
    </div>
</x-layouts.admin>
