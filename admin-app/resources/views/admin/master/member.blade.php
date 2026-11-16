<x-layouts.admin :title="$member->exists ? 'Ubah anggota' : 'Tambah anggota'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('members.index') }}">Anggota Tim</a></div>
        <h1>{{ $member->exists ? 'Ubah '.$member->name : 'Tambah anggota tim' }}</h1></div></div>
    @php($positionsByCompany = $positions->groupBy('company_id')->map(fn ($g) => $g->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values()))
    <form method="post" action="{{ $member->exists ? route('members.update', $member) : route('members.store') }}" style="max-width:960px"
        data-member-form data-positions="{{ json_encode($positionsByCompany) }}">
        @csrf @if($member->exists) @method('PUT') @endif
        <div class="panel mb-3">
            <h2 class="panel-title">Akun</h2>
            <div class="row g-3">
                <div class="col-md-6"><x-field name="name" label="Nama" :value="$member->name" required/></div>
                <div class="col-md-6"><x-field name="email" label="Email" type="email" :value="$member->email" required/></div>
                <div class="col-md-6"><x-field name="password" label="{{ $member->exists ? 'Kata sandi baru' : 'Kata sandi (opsional)' }}" type="password"
                    help="{{ $member->exists ? 'Kosongkan bila tidak diganti. Minimal 12 karakter dengan huruf dan angka.' : 'Kosongkan: sandi awal dibuat otomatis dan ditampilkan sekali setelah disimpan.' }}"/></div>
                <div class="col-md-6"><x-field name="phone" label="Telepon" :value="$member->profile?->phone"/></div>
                <div class="col-md-6"><x-field name="role" label="Role" type="select" :options="$roles" :value="$member->roles->first()?->slug ?? 'member'" required help="Role menentukan hak akses. Jabatan diatur per divisi di bawah."/></div>
                <div class="col-md-6 d-flex align-items-end"><x-switch label="Aktif (bisa masuk)" :checked="$member->exists ? (bool) $member->profile?->is_active : true"/></div>
            </div>
        </div>
        <div class="panel mb-3">
            <h2 class="panel-title">Company</h2>
            @error('companies')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <div class="d-flex flex-wrap gap-3">
                @foreach($companies as $c)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="companies[]" id="c-{{ $c->id }}" value="{{ $c->id }}" data-company-box data-name="{{ $c->name }}" @checked(in_array($c->id, old('companies', $currentCompanies)))>
                        <label class="form-check-label" for="c-{{ $c->id }}">{{ $c->name }}</label></div>
                @endforeach
            </div>
        </div>
        <div class="panel mb-3">
            <h2 class="panel-title">Divisi dan jabatan</h2>
            <p class="muted small">Satu orang boleh di beberapa divisi. Yang tampil hanya divisi dari company yang dicentang di atas.</p>
            @error('divisions')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
            <div class="table-responsive"><table class="table align-middle">
                <thead><tr><th scope="col">Ikut</th><th scope="col">Divisi</th><th scope="col">Company</th><th scope="col">Jabatan</th></tr></thead>
                <tbody data-division-rows>
                @forelse($divisions as $d)
                    @php($included = old("divisions.{$d->id}.included", array_key_exists($d->id, $currentDivisions)))
                    <tr data-division-row data-company="{{ $d->company_id }}">
                        <td><input type="hidden" name="divisions[{{ $d->id }}][included]" value="0"><input class="form-check-input" type="checkbox" name="divisions[{{ $d->id }}][included]" value="1" id="d-{{ $d->id }}" @checked($included) aria-label="Ikut divisi {{ $d->name }}"></td>
                        <td><label class="form-check-label" for="d-{{ $d->id }}">{{ $d->name }}</label></td>
                        <td class="muted">{{ $d->company?->name }}</td>
                        <td><label class="visually-hidden" for="p-{{ $d->id }}">Jabatan di {{ $d->name }}</label>
                            <select class="form-select form-select-sm" name="divisions[{{ $d->id }}][position_id]" id="p-{{ $d->id }}" data-position-select data-company="{{ $d->company_id }}">
                                <option value="">Tanpa jabatan</option>
                                @foreach($positions->where('company_id', $d->company_id) as $p)
                                    <option value="{{ $p->id }}" @selected((string) old("divisions.{$d->id}.position_id", $currentDivisions[$d->id] ?? '') === (string) $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select></td>
                    </tr>
                @empty
                @endforelse
                    <tr data-no-division @if($divisions->isNotEmpty()) hidden @endif><td colspan="4" class="muted">Belum ada divisi untuk company yang dicentang.</td></tr>
                </tbody>
            </table></div>
            {{-- U5.1: a missing division or position is added here, without leaving the form. --}}
            @if(auth()->user()->can('divisions.manage') || auth()->user()->can('positions.manage'))
                <div class="d-flex flex-wrap gap-2">
                    @can('divisions.manage')<button type="button" class="quick-add-link" data-org-add="division" data-url="{{ route('divisions.store') }}" data-title="Tambah divisi"><x-icon name="plus" :size="16"/>Divisi baru</button>@endcan
                    @can('positions.manage')<button type="button" class="quick-add-link" data-org-add="position" data-url="{{ route('positions.store') }}" data-title="Tambah jabatan"><x-icon name="plus" :size="16"/>Jabatan baru</button>@endcan
                </div>
            @endif
        </div>
        <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Simpan</button><a class="btn btn-outline-secondary" href="{{ route('members.index') }}">Batal</a></div>
    </form>
    <div class="modal fade" id="org-add-modal" tabindex="-1" aria-labelledby="org-add-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" novalidate>
                <div class="modal-header border-0 pb-0"><h2 class="modal-title h5" id="org-add-title" data-org-title>Tambah</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label" for="org-company">Company <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select" id="org-company" name="company_id" required aria-describedby="org-company-error">
                            @foreach($companies as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select>
                        <div class="invalid-feedback" id="org-company-error" data-error-for="company_id"></div></div>
                    <div class="mb-1"><label class="form-label" for="org-name">Nama <span class="text-danger" aria-hidden="true">*</span></label>
                        <input class="form-control" id="org-name" name="name" required maxlength="150" autocomplete="off" aria-describedby="org-name-error">
                        <div class="invalid-feedback" id="org-name-error" data-error-for="name"></div></div>
                    <p class="form-text mb-0" data-org-help></p>
                    <div class="alert alert-danger mt-3 mb-0 d-none" role="alert" data-error-general></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
