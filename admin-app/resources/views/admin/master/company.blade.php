<x-layouts.admin :title="$company->exists ? 'Ubah company' : 'Tambah company'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('companies.index') }}">Company</a></div>
        <h1>{{ $company->exists ? 'Ubah '.$company->name : 'Tambah company' }}</h1></div></div>
    <form method="post" action="{{ $company->exists ? route('companies.update', $company) : route('companies.store') }}" class="panel" style="max-width:760px">
        @csrf @if($company->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8"><x-field name="name" label="Nama" :value="$company->name" required/></div>
            <div class="col-md-4"><x-field name="code" label="Kode" :value="$company->code" data-code-from="name" :data-code-url="route('codes.suggest', ['for' => 'companies'])" :data-code-auto="old('code_auto')" help="Terisi otomatis dari nama. Boleh diganti."/></div>
            <div class="col-md-6"><x-field name="email" label="Email" type="email" :value="$company->email"/></div>
            <div class="col-md-6"><x-field name="phone" label="Telepon" :value="$company->phone"/></div>
            <div class="col-12"><x-field name="address" label="Alamat" type="textarea" :value="$company->address"/></div>
        </div>
        <x-switch :checked="(bool) ($company->is_active ?? true)" help="Matikan bila sudah tidak dipakai; data lama tetap tersimpan."/>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('companies.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
