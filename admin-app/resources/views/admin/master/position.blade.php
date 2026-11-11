<x-layouts.admin :judul="$position->exists ? 'Ubah jabatan' : 'Tambah jabatan'">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('positions.index') }}">Jabatan</a></div>
        <h1>{{ $position->exists ? 'Ubah jabatan '.$position->name : 'Tambah jabatan' }}</h1></div></div>
    <form method="post" action="{{ $position->exists ? route('positions.update', $position) : route('positions.store') }}" class="kartu" style="max-width:760px">
        @csrf @if ($position->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><x-isian nama="company_id" label="Company" jenis="select" :pilihan="$companies" :nilai="$position->company_id" kosong="Pilih company" wajib/></div>
            <div class="col-md-6"><x-isian nama="name" label="Nama jabatan" :nilai="$position->name" wajib/></div>
            <div class="col-12"><x-isian nama="description" label="Keterangan" jenis="textarea" :nilai="$position->description"/></div>
        </div>
        <div class="form-check mb-3"><input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $position->is_active))>
            <label class="form-check-label" for="is_active">Aktif</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('positions.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
