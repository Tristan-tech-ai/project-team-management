<x-layouts.admin judul="Monitoring Divisi & Anggota">
    <div class="kepala-halaman"><div><div class="remah">Management</div><h1>Monitoring Divisi &amp; Anggota</h1><p>Beban kerja, keterlibatan, dan durasi yang tercatat. Anggota unik dihitung sekali walau ada di beberapa divisi.</p></div>
        @if (auth()->user()->hasPermission('management.export'))<a class="btn btn-outline-primary" href="{{ route('management.export', ['jenis' => 'anggota']) }}"><x-ikon name="external-link"/> Export CSV</a>@endif</div>
    <div class="kartu mb-3"><h2 class="kartu-judul">Per divisi</h2>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th scope="col">Divisi</th><th scope="col">Company</th><th scope="col" class="angka">Anggota unik</th><th scope="col" class="angka">Project terlibat</th><th scope="col" class="angka">Task aktif anggota</th></tr></thead>
            <tbody>@forelse ($divisi as $d)<tr><td><a href="{{ route('management.teams', ['division_id' => $d->id]) }}">{{ $d->name }}</a></td><td>{{ $d->company }}</td><td class="angka">{{ $d->anggota }}</td><td class="angka">{{ $d->project }}</td><td class="angka">{{ $d->task_aktif }}</td></tr>
            @empty<tr><td colspan="5" class="teks-2">Belum ada divisi.</td></tr>@endforelse</tbody>
        </table></div></div>
    <div class="kartu">
        <form method="get" class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-2">
            <h2 class="kartu-judul mb-0">Per anggota</h2>
            <div class="d-flex gap-2 align-items-end"><div><label class="form-label" for="dv">Divisi</label><select id="dv" name="division_id" class="form-select"><option value="">Semua</option>@foreach ($pilihanDivisi as $k => $v)<option value="{{ $k }}" @selected($divDipilih == $k)>{{ $v }}</option>@endforeach</select></div>
                <button class="btn btn-outline-secondary" type="submit">Saring</button></div>
        </form>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th scope="col">Anggota</th><th scope="col" class="angka">Project</th><th scope="col" class="angka">Task aktif</th><th scope="col" class="angka">Terlambat</th><th scope="col" class="angka">Selesai</th><th scope="col" class="angka">Jam kerja tercatat</th><th scope="col" class="angka">Jam aktivitas</th></tr></thead>
            <tbody>@forelse ($orang as $o)<tr><td>{{ $o->name }}</td><td class="angka">{{ $o->project }}</td><td class="angka">{{ $o->aktif }}</td><td class="angka {{ $o->terlambat ? 'text-danger fw-semibold' : '' }}">{{ $o->terlambat }}</td>
                <td class="angka">{{ $o->selesai }}</td><td class="angka">{{ number_format($o->menit_kerja / 60, 1, ',', '.') }}</td><td class="angka">{{ number_format($o->menit_aktivitas / 60, 1, ',', '.') }}</td></tr>
            @empty<tr><td colspan="7" class="teks-2">Belum ada anggota project.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</x-layouts.admin>
