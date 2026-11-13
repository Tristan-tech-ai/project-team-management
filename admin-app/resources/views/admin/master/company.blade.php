<x-layouts.admin :title="$company->exists ? 'Ubah company' : 'Tambah company'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('companies.index') }}">Company</a></div>
        <h1>{{ $company->exists ? 'Ubah '.$company->name : 'Tambah company' }}</h1></div></div>
    <form method="post" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}" class="panel" style="max-width:760px">
        @csrf @if($company->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-4"><x-field name="code" label="Kode" :value="$company->code" required help="Huruf, angka, titik, garis bawah, atau strip. Contoh: COMP-A"/></div>
            <div class="col-md-8"><x-field name="name" label="Nama" :value="$company->name" required/></div>
            <div class="col-md-6"><x-field name="email" label="Email" type="email" :value="$company->email"/></div>
            <div class="col-md-6"><x-field name="phone" label="Telepon" :value="$company->phone"/></div>
            <div class="col-12"><x-field name="address" label="Alamat" type="textarea" :value="$company->address"/></div>
        </div>
        <div class="form-check mb-3"><input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $company->is_active))>
            <label class="form-check-label" for="is_active">Aktif</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('companies.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
