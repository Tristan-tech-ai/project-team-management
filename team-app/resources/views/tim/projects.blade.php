@php use App\Support\Kode; use App\Support\Waktu; @endphp
<x-layouts.tim judul="Project Saya">
    <div class="kepala-halaman"><div><h1 class="mb-0">Project Saya</h1><p>Project tempat kamu menjadi anggota.</p></div></div>
    <form method="get" class="mb-3" role="search"><label class="visually-hidden" for="cari">Cari project</label>
        <div class="d-flex gap-2"><input id="cari" type="search" name="cari" value="{{ request('cari') }}" class="form-control" placeholder="Cari project"><button class="btn btn-outline-secondary" type="submit"><x-ikon name="search"/><span class="visually-hidden">Cari</span></button></div></form>
    <div class="daftar-task">
        @forelse ($projects as $p)
            @php($persen = $p->task_utama ? round($p->task_selesai * 100 / $p->task_utama) : 0)
            <div class="kartu">
                <div class="d-flex justify-content-between gap-2"><div class="small teks-2">{{ $p->client?->name ?? 'Internal' }} · {{ Kode::label(Kode::PROJECT_TYPE, $p->project_type) }}</div><x-lencana :kode="$p->status" :teks="Kode::label(Kode::PROJECT_STATUS, $p->status)"/></div>
                <a class="fw-semibold fs-6 d-block mt-1" href="{{ route('projects.show', $p) }}">{{ $p->name }}</a>
                <div class="d-flex align-items-center gap-2 mt-2"><div class="progres flex-grow-1"><span style="width:{{ $persen }}%"></span></div><span class="small angka">{{ $persen }}%</span></div>
                <div class="small teks-2 mt-1">PIC {{ $p->pic?->name }} · deadline {{ Waktu::tanggal($p->due_at) }}</div>
                <div class="d-flex gap-2 mt-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('projects.board', $p) }}">Board</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('projects.show', $p) }}">Ringkasan</a></div>
            </div>
        @empty
            <div class="kartu"><p class="mb-0 teks-2">{{ request('cari') ? 'Tidak ada project dengan nama itu.' : 'Kamu belum menjadi anggota project. Minta PIC atau manager menambahkanmu.' }}</p></div>
        @endforelse
    </div>
    <div class="mt-3">{{ $projects->links() }}</div>
</x-layouts.tim>
