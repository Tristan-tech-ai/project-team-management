<x-layouts.admin :judul="$division->exists ? 'Ubah divisi' : 'Tambah divisi'">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('divisions.index') }}">Divisi</a></div>
        <h1>{{ $division->exists ? 'Ubah divisi '.$division->name : 'Tambah divisi' }}</h1></div></div>
    <form method="post" action="{{ $division->exists ? route('divisions.update', $division) : route('divisions.store') }}" class="kartu" style="max-width:760px">
        @csrf @if ($division->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><x-isian nama="company_id" label="Company" jenis="select" :pilihan="$companies" :nilai="$division->company_id" kosong="Pilih company" wajib/></div>
            <div class="col-md-6"><x-isian nama="name" label="Nama divisi" :nilai="$division->name" wajib/></div>
            <div class="col-md-6"><x-isian nama="lead_user_id" label="Kepala divisi" jenis="select" :pilihan="$users" :nilai="$division->lead_user_id" kosong="Belum ada" bantuan="Harus anggota aktif company ini."/></div>
            <div class="col-12"><x-isian nama="description" label="Keterangan" jenis="textarea" :nilai="$division->description"/></div>
        </div>
        <div class="form-check mb-3"><input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $division->is_active))>
            <label class="form-check-label" for="is_active">Aktif</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('divisions.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
