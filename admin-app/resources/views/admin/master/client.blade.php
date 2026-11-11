<x-layouts.admin :judul="$client->exists ? 'Ubah client' : 'Tambah client'">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('clients.index') }}">Client</a></div>
        <h1>{{ $client->exists ? 'Ubah '.$client->name : 'Tambah client' }}</h1></div></div>
    <form method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="kartu" style="max-width:760px">
        @csrf @if ($client->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-4"><x-isian nama="code" label="Kode" :nilai="$client->code" wajib bantuan="Contoh: CLIENT-A"/></div>
            <div class="col-md-8"><x-isian nama="name" label="Nama" :nilai="$client->name" wajib/></div>
            <div class="col-md-6"><x-isian nama="email" label="Email" jenis="email" :nilai="$client->email"/></div>
            <div class="col-md-6"><x-isian nama="phone" label="Telepon" :nilai="$client->phone"/></div>
            <div class="col-12"><x-isian nama="website" label="Situs" jenis="url" :nilai="$client->website" bantuan="Diawali https://"/></div>
            <div class="col-12"><x-isian nama="address" label="Alamat" jenis="textarea" :nilai="$client->address"/></div>
            <div class="col-12"><x-isian nama="notes" label="Catatan" jenis="textarea" :nilai="$client->notes"/></div>
        </div>
        <div class="form-check mb-3"><input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $client->is_active))>
            <label class="form-check-label" for="is_active">Aktif</label></div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
