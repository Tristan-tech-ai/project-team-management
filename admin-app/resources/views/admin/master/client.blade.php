<x-layouts.admin :title="$client->exists ? 'Ubah client' : 'Tambah client'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('clients.index') }}">Client</a></div>
        <h1>{{ $client->exists ? 'Ubah '.$client->name : 'Tambah client' }}</h1></div></div>
    <form method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="panel" style="max-width:760px">
        @csrf @if($client->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-4"><x-field name="code" label="Kode" :value="$client->code" required help="Contoh: CLIENT-A"/></div>
            <div class="col-md-8"><x-field name="name" label="Nama" :value="$client->name" required/></div>
            <div class="col-md-6"><x-field name="email" label="Email" type="email" :value="$client->email"/></div>
            <div class="col-md-6"><x-field name="phone" label="Telepon" :value="$client->phone"/></div>
            <div class="col-12"><x-field name="website" label="Situs" type="url" :value="$client->website" help="Diawali https://"/></div>
            <div class="col-12"><x-field name="address" label="Alamat" type="textarea" :value="$client->address"/></div>
            <div class="col-12"><x-field name="notes" label="Catatan" type="textarea" :value="$client->notes"/></div>
        </div>
        <div class="form-check mb-3"><input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $client->is_active))>
            <label class="form-check-label" for="is_active">Aktif</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
