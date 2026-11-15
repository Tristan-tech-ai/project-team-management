<x-layouts.admin :title="$division->exists ? 'Ubah divisi' : 'Tambah divisi'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('divisions.index') }}">Divisi</a></div>
        <h1>{{ $division->exists ? 'Ubah divisi '.$division->name : 'Tambah divisi' }}</h1></div></div>
    <form method="post" action="{{ $division->exists ? route('divisions.update', $division) : route('divisions.store') }}" class="panel" style="max-width:760px">
        @csrf @if($division->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-6"><x-field name="company_id" label="Company" type="select" :options="$companies" :value="$division->company_id" empty="Pilih company" required/></div>
            <div class="col-md-6"><x-field name="name" label="Nama divisi" :value="$division->name" required/></div>
            <div class="col-md-6"><x-field name="lead_user_id" label="Kepala divisi" type="select" :options="$users" :value="$division->lead_user_id" empty="Belum ada" help="Harus anggota aktif company ini."/></div>
            <div class="col-12"><x-field name="description" label="Keterangan" type="textarea" :value="$division->description"/></div>
        </div>
        <x-switch :checked="(bool) ($division->is_active ?? true)" help="Matikan bila sudah tidak dipakai; data lama tetap tersimpan."/>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('divisions.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
