<x-layouts.admin :judul="$member->exists ? 'Ubah anggota' : 'Tambah anggota'">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('members.index') }}">Anggota Tim</a></div>
        <h1>{{ $member->exists ? 'Ubah '.$member->name : 'Tambah anggota tim' }}</h1></div></div>
    <form method="post" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" style="max-width:960px">
        @csrf @if ($member->exists) @method('PUT') @endif
        <div class="kartu mb-3">
            <h2 class="kartu-judul">Akun</h2>
            <div class="row g-3">
                <div class="col-md-6"><x-isian nama="name" label="Nama" :nilai="$member->name" wajib/></div>
                <div class="col-md-6"><x-isian nama="email" label="Email" jenis="email" :nilai="$member->email" wajib/></div>
                <div class="col-md-6"><x-isian nama="password" label="{{ $member->exists ? 'Kata sandi baru' : 'Kata sandi' }}" jenis="password" :wajib="! $member->exists"
                    bantuan="{{ $member->exists ? 'Kosongkan bila tidak diganti. ' : '' }}Minimal 12 karakter dengan huruf dan angka."/></div>
                <div class="col-md-6"><x-isian nama="phone" label="Telepon" :nilai="$member->profile?->phone"/></div>
                <div class="col-md-6"><x-isian nama="role" label="Role" jenis="select" :pilihan="$roles" :nilai="$member->roles->first()?->slug ?? 'member'" wajib bantuan="Role menentukan hak akses. Jabatan diatur per divisi di bawah."/></div>
                <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-3"><input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $member->exists ? (bool) $member->profile?->is_active : true))>
                    <label class="form-check-label" for="is_active">Aktif (bisa masuk)</label></div></div>
            </div>
        </div>
        <div class="kartu mb-3">
            <h2 class="kartu-judul">Company</h2>
            @error('companies')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <div class="d-flex flex-wrap gap-3">
                @foreach ($companies as $c)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="companies[]" id="c-{{ $c->id }}" value="{{ $c->id }}" @checked(in_array($c->id, old('companies', $companyIni)))>
                        <label class="form-check-label" for="c-{{ $c->id }}">{{ $c->name }}</label></div>
                @endforeach
            </div>
        </div>
        <div class="kartu mb-3">
            <h2 class="kartu-judul">Divisi dan jabatan</h2>
            <p class="teks-2 small">Satu orang boleh di beberapa divisi. Divisi harus milik company yang dicentang di atas.</p>
            @error('divisi')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Ikut</th><th scope="col">Divisi</th><th scope="col">Company</th><th scope="col">Jabatan</th></tr></thead>
                <tbody>
                @forelse ($divisions as $d)
                    @php($ikut = old("divisi.{$d->id}.ikut", array_key_exists($d->id, $divisiIni)))
                    <tr>
                        <td><input type="hidden" name="divisi[{{ $d->id }}][ikut]" value="0"><input class="form-check-input" type="checkbox" name="divisi[{{ $d->id }}][ikut]" value="1" id="d-{{ $d->id }}" @checked($ikut) aria-label="Ikut divisi {{ $d->name }}"></td>
                        <td><label for="d-{{ $d->id }}">{{ $d->name }}</label></td>
                        <td class="teks-2">{{ $d->company?->name }}</td>
                        <td><label class="visually-hidden" for="p-{{ $d->id }}">Jabatan di {{ $d->name }}</label>
                            <select class="form-select form-select-sm" name="divisi[{{ $d->id }}][position_id]" id="p-{{ $d->id }}">
                                <option value="">Tanpa jabatan</option>
                                @foreach ($positions->where('company_id', $d->company_id) as $p)
                                    <option value="{{ $p->id }}" @selected((string) old("divisi.{$d->id}.position_id", $divisiIni[$d->id] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="teks-2">Belum ada divisi aktif. Tambahkan di Master Data, Divisi.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('members.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
