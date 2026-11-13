<x-layouts.admin :title="$member->exists ? 'Ubah anggota' : 'Tambah anggota'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('members.index') }}">Anggota Tim</a></div>
        <h1>{{ $member->exists ? 'Ubah '.$member->name : 'Tambah anggota tim' }}</h1></div></div>
    <form method="post" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" style="max-width:960px">
        @csrf @if($member->exists) @method('PUT') @endif
        <div class="panel mb-3">
            <h2 class="panel-title">Akun</h2>
            <div class="row g-3">
                <div class="col-md-6"><x-field name="name" label="Nama" :value="$member->name" required/></div>
                <div class="col-md-6"><x-field name="email" label="Email" type="email" :value="$member->email" required/></div>
                <div class="col-md-6"><x-field name="password" label="{{ $member->exists ? 'Kata sandi baru' : 'Kata sandi' }}" type="password" :required="! $member->exists"
                    help="{{ $member->exists ? 'Kosongkan bila tidak diganti. ' : '' }}Minimal 12 karakter dengan huruf dan angka."/></div>
                <div class="col-md-6"><x-field name="phone" label="Telepon" :value="$member->profile?->phone"/></div>
                <div class="col-md-6"><x-field name="role" label="Role" type="select" :options="$roles" :value="$member->roles->first()?->slug ?? 'member'" required help="Role menentukan hak akses. Jabatan diatur per divisi di bawah."/></div>
                <div class="col-md-6 d-flex align-items-end"><div class="form-check mb-3"><input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $member->exists ? (bool) $member->profile?->is_active : true))>
                    <label class="form-check-label" for="is_active">Aktif (bisa masuk)</label></div></div>
            </div>
        </div>
        <div class="panel mb-3">
            <h2 class="panel-title">Company</h2>
            @error('companies')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <div class="d-flex flex-wrap gap-3">
                @foreach($companies as $c)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="companies[]" id="c-{{ $c->id }}" value="{{ $c->id }}" @checked(in_array($c->id, old('companies', $currentCompanies)))>
                        <label class="form-check-label" for="c-{{ $c->id }}">{{ $c->name }}</label></div>
                @endforeach
            </div>
        </div>
        <div class="panel mb-3">
            <h2 class="panel-title">Divisi dan jabatan</h2>
            <p class="muted small">Satu orang boleh di beberapa divisi. Divisi harus milik company yang dicentang di atas.</p>
            @error('divisions')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Ikut</th><th scope="col">Divisi</th><th scope="col">Company</th><th scope="col">Jabatan</th></tr></thead>
                <tbody>
                @forelse($divisions as $d)
                    @php($included = old("divisions.{$d->id}.included", array_key_exists($d->id, $currentDivisions)))
                    <tr>
                        <td><input type="hidden" name="divisions[{{ $d->id }}][included]" value="0"><input class="form-check-input" type="checkbox" name="divisions[{{ $d->id }}][included]" value="1" id="d-{{ $d->id }}" @checked($included) aria-label="Ikut divisi {{ $d->name }}"></td>
                        <td><label class="form-check-label" for="d-{{ $d->id }}">{{ $d->name }}</label></td>
                        <td class="muted">{{ $d->company?->name }}</td>
                        <td><label class="visually-hidden" for="p-{{ $d->id }}">Jabatan di {{ $d->name }}</label>
                            <select class="form-select form-select-sm" name="divisions[{{ $d->id }}][position_id]" id="p-{{ $d->id }}">
                                <option value="">Tanpa jabatan</option>
                                @foreach($positions->where('company_id', $d->company_id) as $p)
                                    <option value="{{ $p->id }}" @selected((string) old("divisions.{$d->id}.position_id", $currentDivisions[$d->id] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Belum ada divisi aktif. Tambahkan di Master Data, Divisi.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('members.index') }}">Batal</a></div>
    </form>
</x-layouts.admin>
