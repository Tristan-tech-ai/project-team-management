@php use App\Support\Kode; use App\Support\Waktu; @endphp
<x-layouts.tim judul="Beranda">
    <div class="kepala-halaman"><div><h1 class="mb-0">Halo, {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</h1><p>{{ Waktu::tanggal(now()) }}</p></div></div>
    <div class="ringkas-status mb-3">
        @foreach (Kode::TASK_STATUS as $k => $v)
            <a class="kartu text-decoration-none text-reset" href="{{ route('tasks.index', ['status' => $k]) }}"><div class="small teks-2">{{ $v }}</div><div class="angka-besar">{{ $hitung[$k] ?? 0 }}</div></a>
        @endforeach
    </div>
    @if ($revisi->isNotEmpty())
        <div class="alert alert-warning" role="status"><strong>Perlu revisi:</strong>
            @foreach ($revisi as $r)<a href="{{ route('tasks.show', $r->task_id) }}">{{ $r->task->title }}</a>@if(! $loop->last), @endif @endforeach</div>
    @endif
    @if ($menungguSaya)
        <a class="kartu d-flex align-items-center justify-content-between mb-3 text-decoration-none text-reset" href="{{ route('review.index') }}">
            <span><x-ikon name="clipboard-check"/> <strong class="angka">{{ $menungguSaya }}</strong> hasil menunggu review-mu</span><x-ikon name="chevron-down" style="transform:rotate(-90deg)"/></a>
    @endif
    <section class="mb-3" aria-labelledby="h-hari-ini">
        <h2 class="h6 mb-2" id="h-hari-ini">Deadline hari ini</h2>
        <div class="daftar-task">@forelse ($hariIni as $t) @include('tim._kartu', ['t' => $t]) @empty<p class="small teks-2 mb-0">Tidak ada deadline hari ini.</p>@endforelse</div>
    </section>
    @if ($terlambat->isNotEmpty())
        <section class="mb-3" aria-labelledby="h-telat"><h2 class="h6 mb-2 text-danger" id="h-telat">Terlambat</h2>
            <div class="daftar-task">@foreach ($terlambat as $t) @include('tim._kartu', ['t' => $t]) @endforeach</div></section>
    @endif
    @if ($diReview->isNotEmpty())
        <section class="mb-3" aria-labelledby="h-review"><h2 class="h6 mb-2" id="h-review">Sedang direview</h2>
            <div class="daftar-task">@foreach ($diReview as $t) @include('tim._kartu', ['t' => $t]) @endforeach</div></section>
    @endif
    <section aria-labelledby="h-project">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0" id="h-project">Project saya</h2><a class="small" href="{{ route('projects.index') }}">Semua project</a></div>
        <div class="daftar-task">@forelse ($projects as $p)
            <a class="kartu text-decoration-none text-reset" href="{{ route('projects.board', $p) }}"><div class="fw-semibold">{{ $p->name }}</div><div class="small teks-2">{{ Kode::label(Kode::PROJECT_TYPE, $p->project_type) }} · Buka board</div></a>
        @empty<p class="small teks-2 mb-0">Kamu belum menjadi anggota project.</p>@endforelse</div>
        <a class="btn btn-outline-primary mt-3" href="{{ route('activities.index') }}"><x-ikon name="activity"/> Aktivitas saya</a>
    </section>
</x-layouts.tim>
