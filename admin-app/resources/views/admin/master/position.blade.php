<x-layouts.admin :title="$position->exists ? 'Ubah jabatan' : 'Tambah jabatan'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('positions.index') }}">Jabatan</a></div>
        <h1>{{ $position->exists ? 'Ubah jabatan '.$position->name : 'Tambah jabatan' }}</h1></div></div>
    <form method="post" action="{{ $position->exists ? route('positions.update', $position) : route('positions.store') }}" class="panel" style="max-width:760px">
        @csrf @if($position->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><x-field name="company_id" label="Company" type="select" :options="$companies" :value="$position->company_id" empty="Pilih company" required/></div>
            <div class="col-md-6"><x-field name="name" label="Nama jabatan" :value="$position->name" required/></div>
            <div class="col-12"><x-field name="description" label="Keterangan" type="textarea" :value="$position->description"/></div>
        </div>
        <x-switch :checked="(bool) ($position->is_active ?? true)" help="Matikan bila sudah tidak dipakai; data lama tetap tersimpan."/>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('positions.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
