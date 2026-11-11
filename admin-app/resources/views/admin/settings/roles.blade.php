<x-layouts.admin judul="Roles & Permissions">
    <div class="kepala-halaman"><div><div class="remah">System Settings</div><h1>Roles &amp; Permissions</h1>
        <p>Izin per role. Menu hanya menampilkan yang diizinkan; server tetap memeriksa izin di setiap permintaan.</p></div></div>
    <div class="row g-3">
        @foreach ($roles as $role)
            @php($milik = $role->permissions->pluck('id')->all())
            <div class="col-xl-4">
                <form method="post" action="{{ route('settings.roles.update', $role) }}" class="kartu h-100">
                    @csrf @method('PUT')
                    <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                        <div><h2 class="kartu-judul mb-1">{{ $role->name }}</h2><div class="teks-2 small">{{ $role->description }}</div></div>
                        <span class="lencana lencana-info angka">{{ $role->slug === 'super_admin' ? 'Semua' : count($milik) }} izin</span>
                    </div>
                    @foreach ($modul as $nama => $izin)
                        <fieldset class="mt-3"><legend class="small fw-semibold text-uppercase teks-2" style="font-size:.75rem">{{ $nama }}</legend>
                            @foreach ($izin as $p)
                                <div class="form-check"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $p->id }}" id="r{{ $role->id }}-p{{ $p->id }}"
                                        @checked($role->slug === 'super_admin' || in_array($p->id, $milik)) @disabled(! $bolehUbah || $role->slug === 'super_admin')>
                                    <label class="form-check-label small" for="r{{ $role->id }}-p{{ $p->id }}">{{ $p->name }} <span class="teks-2">({{ $p->slug }})</span></label></div>
                            @endforeach
                        </fieldset>
                    @endforeach
                    @if ($bolehUbah && $role->slug !== 'super_admin')
                        <button class="btn btn-primary mt-3" type="submit">Simpan izin {{ $role->name }}</button>
                    @elseif ($role->slug === 'super_admin')
                        <p class="teks-2 small mt-3 mb-0">Super Admin selalu memegang semua izin.</p>
                    @endif
                </form>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
