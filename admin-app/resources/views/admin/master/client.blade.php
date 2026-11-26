<x-layouts.admin :title="$client->exists ? 'Ubah client' : 'Tambah client'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('clients.index') }}">Client</a></div>
        <h1>{{ $client->exists ? 'Ubah '.$client->name : 'Tambah client' }}</h1></div></div>
    <form method="post" action="{{ $client->exists ? route('clients.update', $client) : route('clients.store') }}" class="panel" style="max-width:760px">
        @csrf @if($client->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-md-8"><x-field name="name" label="Nama" :value="$client->name" required/></div>
            <div class="col-md-4"><x-field name="code" label="Kode" :value="$client->code" data-code-from="name" :data-code-url="route('codes.suggest', ['for' => 'clients'])" :data-code-auto="old('code_auto')" help="Terisi otomatis dari nama. Boleh diganti."/></div>
            <div class="col-md-6"><x-field name="email" label="Email" type="email" :value="$client->email"/></div>
            <div class="col-md-6"><x-field name="phone" label="Telepon" :value="$client->phone"/></div>
            <div class="col-12"><x-field name="website" label="Situs" type="link" :value="$client->website" help="Boleh tanpa https://, misalnya kopisenja.id."/></div>
            @php($checked = array_map('intval', (array) old('companies', $linked)))
            <div class="col-12"><fieldset class="mb-3">
                <legend class="form-label">Company yang melayani client ini</legend>
                @forelse($companies as $id => $name)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="companies[]" value="{{ $id }}" id="cc-{{ $id }}" @checked(in_array((int) $id, $checked, true))>
                        <label class="form-check-label" for="cc-{{ $id }}">{{ $name }}</label></div>
                @empty
                    <p class="form-text mb-0">Belum ada company.</p>
                @endforelse
                <div class="form-text">Saat membuat project, client hanya muncul di company yang dicentang. Biarkan kosong bila client umum dan boleh dipakai semua company.</div>
                @error('companies.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </fieldset></div>
            <div class="col-12"><x-field name="address" label="Alamat" type="textarea" :value="$client->address"/></div>
            <div class="col-12"><x-field name="notes" label="Catatan" type="textarea" :value="$client->notes"/></div>
        </div>
        <x-switch :checked="(bool) ($client->is_active ?? true)" help="Matikan bila sudah tidak dipakai; data lama tetap tersimpan."/>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('clients.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
