@php use App\Support\Kode; use App\Support\Waktu; $pt = $kpi['per_tipe']; @endphp
<x-layouts.admin judul="Management Overview">
    <div class="kepala-halaman"><div><div class="remah">Management</div><h1>Management Overview</h1><p>Pantau prioritas dan aktivitas kerja lintas divisi.</p></div></div>
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><div class="kartu h-100"><div class="teks-2">Project aktif</div><div class="kpi-nilai" style="color:var(--ptm-aksi)">{{ $kpi['project_aktif'] }}</div>
            <div class="small teks-2">{{ $pt['one_time'] ?? 0 }} sekali jadi · {{ $pt['recurring'] ?? 0 }} berulang · {{ $pt['ongoing'] ?? 0 }} berkelanjutan</div></div></div>
        <div class="col-6 col-xl-3"><div class="kartu h-100"><div class="teks-2">Task berjalan</div><div class="kpi-nilai" style="color:var(--ptm-aksi)">{{ $kpi['task_berjalan'] }}</div><div class="small teks-2">dari {{ $kpi['task_utama'] }} task utama</div></div></div>
        <div class="col-6 col-xl-3"><div class="kartu h-100"><div class="teks-2">Terlambat</div><div class="kpi-nilai" style="color:var(--ptm-telat)">{{ $kpi['terlambat'] }}</div><div class="small teks-2">lewat deadline, belum selesai</div></div></div>
        <div class="col-6 col-xl-3"><div class="kartu h-100"><div class="teks-2">Menunggu review</div><div class="kpi-nilai" style="color:var(--ptm-review)">{{ $kpi['review'] }}</div><div class="small teks-2">butuh keputusan PIC</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-8"><div class="kartu h-100">
            <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="kartu-judul mb-0">Monitoring Project</h2><a class="small" href="{{ route('management.projects') }}">Lihat semua</a></div>
            @include('admin.management._monitor', ['baris' => $monitor])
        </div></div>
        <div class="col-xl-4"><div class="kartu h-100">
            <h2 class="kartu-judul">Perlu perhatian</h2>
            <a class="d-block rounded-3 p-3 mb-2 text-decoration-none" style="background:var(--ptm-review-lembut);color:var(--ptm-review)" href="{{ route('activities.impacts') }}"><strong class="angka">{{ $kpi['jadwal_menunggu'] }}</strong> perubahan jadwal<div class="small" style="color:var(--ptm-ink-2)">Menunggu persetujuan</div></a>
            <a class="d-block rounded-3 p-3 mb-2 text-decoration-none" style="background:var(--ptm-aksi-lembut);color:var(--ptm-aksi-gelap)" href="{{ route('activities.index') }}"><strong class="angka">{{ $kpi['aktivitas_7_hari'] }}</strong> kegiatan 7 hari terakhir<div class="small" style="color:var(--ptm-ink-2)">Rapat, kunjungan, revisi, hambatan</div></a>
            <a class="d-block rounded-3 p-3 mb-2 text-decoration-none" style="background:var(--ptm-telat-lembut);color:var(--ptm-telat)" href="{{ route('tasks.index', ['terlambat' => 1]) }}"><strong class="angka">{{ $kpi['terlambat'] }}</strong> task terlambat<div class="small" style="color:var(--ptm-ink-2)">Lihat penyebab dan PIC</div></a>
            <a class="d-block rounded-3 p-3 mb-3 text-decoration-none" style="background:var(--ptm-selesai-lembut);color:var(--ptm-selesai)" href="{{ route('tasks.review') }}"><strong class="angka">{{ $kpi['review'] }}</strong> task review<div class="small" style="color:var(--ptm-ink-2)">Butuh hasil pemeriksaan</div></a>
            <p class="small teks-2 mb-0"><span class="angka">{{ $kpi['anggota_unik'] }}</span> anggota unik memegang <span class="angka">{{ $kpi['penugasan'] }}</span> penugasan task.</p>
        </div></div>
        <div class="col-lg-6"><div class="kartu h-100"><h2 class="kartu-judul">Antrian review</h2>
            @forelse ($review as $s)<div class="mb-2"><a href="{{ route('tasks.show', $s->task_id) }}">{{ $s->task?->title }}</a><div class="small teks-2">{{ $s->submitter?->name }} · {{ Waktu::tanggalJam($s->submitted_at) }}</div></div>
            @empty<p class="small teks-2 mb-0">Tidak ada yang menunggu.</p>@endforelse</div></div>
        <div class="col-lg-6"><div class="kartu h-100"><h2 class="kartu-judul">Operasional terbaru</h2>
            @forelse ($operasional as $a)<div class="mb-2"><span class="fw-semibold">{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</span><div class="small teks-2">{{ $a->project?->name }} · {{ Kode::label(Kode::IMPACT_TYPE, $a->impact_type) }} · {{ Waktu::tanggal($a->occurred_at ?? $a->scheduled_at) }}</div></div>
            @empty<p class="small teks-2 mb-0">Belum ada aktivitas.</p>@endforelse</div></div>
    </div>
</x-layouts.admin>
