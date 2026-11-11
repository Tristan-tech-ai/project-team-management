@php use App\Support\Kode; use App\Support\Waktu; use App\Services\ManagementMetricsService as M; @endphp
<x-layouts.tim :judul="$project->name">
    @include('tim._kepala')
    <div class="row g-3">
        <div class="col-lg-6"><div class="kartu h-100">
            <div class="d-flex justify-content-between align-items-end"><h2 class="kartu-judul mb-0">Progress</h2><span class="kpi-nilai">{{ $progres['persen'] }}%</span></div>
            <div class="progres my-2" style="height:12px"><span style="width:{{ $progres['persen'] }}%"></span></div>
            <p class="small teks-2">{{ $progres['selesai'] }} dari {{ $progres['total'] }} task utama selesai.</p>
            <dl class="row small mb-0">
                <dt class="col-5 teks-2 fw-normal">Kondisi</dt><dd class="col-7"><x-lencana :kode="M::KONDISI_KELAS[$kondisi] ?? 'netral'" :teks="M::KONDISI[$kondisi] ?? $kondisi"/></dd>
                <dt class="col-5 teks-2 fw-normal">Target awal</dt><dd class="col-7">{{ Waktu::tanggal($project->baseline_due_at) }}</dd>
                <dt class="col-5 teks-2 fw-normal">Deadline terbaru</dt><dd class="col-7">{{ Waktu::tanggal($project->due_at) }}</dd>
                <dt class="col-5 teks-2 fw-normal">Client</dt><dd class="col-7">{{ $project->client?->name ?? 'Internal' }}</dd>
            </dl>
        </div></div>
        <div class="col-lg-6"><div class="kartu h-100"><h2 class="kartu-judul">Tim</h2><p class="mb-0">{{ $tim->implode(', ') }}</p>
            @if ($project->description)<h2 class="kartu-judul mt-3">Catatan project</h2><p class="small mb-0" style="white-space:pre-line">{{ $project->description }}</p>@endif</div></div>
        <div class="col-12"><div class="kartu"><h2 class="kartu-judul">Catatan operasional</h2>
            <ul class="timeline">@forelse ($catatan as $a)
                <li class="{{ $a->impact_type === 'blocked' ? 'hambat' : ($a->impact_type !== 'none' ? 'dampak' : '') }}"><div class="small fw-semibold">{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                    <div class="small teks-2">{{ Waktu::tanggalJam($a->occurred_at ?? $a->scheduled_at ?? $a->created_at) }} · {{ $a->creator?->name }} · {{ Kode::label(Kode::IMPACT_TYPE, $a->impact_type) }}</div></li>
            @empty<li class="small teks-2">Belum ada aktivitas.</li>@endforelse</ul></div></div>
    </div>
</x-layouts.tim>
