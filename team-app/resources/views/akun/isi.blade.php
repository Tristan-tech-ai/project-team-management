<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="{{ route('akun.update') }}" enctype="multipart/form-data" class="kartu h-100">
            @csrf @method('PUT')
            <h2 class="kartu-judul">Profil</h2>
            <div class="d-flex align-items-center gap-3 mb-3">
                @if ($user->profile?->avatar_path)
                    <img src="{{ route('akun.foto', ['user' => $user->id, 'v' => substr(md5($user->profile->avatar_path), 0, 8)]) }}" alt="Foto {{ $user->name }}" width="64" height="64" class="rounded-circle object-fit-cover">
                @else
                    <span class="rounded-circle d-grid fw-bold" style="width:64px;height:64px;place-items:center;background:var(--ptm-aksi-lembut);color:var(--ptm-aksi-gelap)" aria-hidden="true">{{ $user->inisial() }}</span>
                @endif
                <div class="teks-2 small">{{ $user->email }}</div>
            </div>
            <x-isian nama="name" label="Nama" :nilai="$user->name" wajib/>
            <x-isian nama="phone" label="Telepon" :nilai="$user->profile?->phone"/>
            <div class="mb-3"><label class="form-label" for="foto">Foto</label>
                <input class="form-control @error('foto') is-invalid @enderror" type="file" id="foto" name="foto" accept="image/jpeg,image/png,image/webp" aria-describedby="foto-b">
                <div id="foto-b" class="form-text">JPG, PNG, atau WebP, paling besar 2 MB.</div>
                @error('foto')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <button class="btn btn-primary" type="submit">Simpan profil</button>
        </form>
    </div>
    <div class="col-lg-6">
        <form method="post" action="{{ route('akun.password') }}" class="kartu mb-3">
            @csrf @method('PUT')
            <h2 class="kartu-judul">Ganti kata sandi</h2>
            <x-isian nama="current_password" label="Kata sandi sekarang" jenis="password" wajib/>
            <x-isian nama="password" label="Kata sandi baru" jenis="password" wajib bantuan="Minimal 12 karakter dengan huruf dan angka."/>
            <x-isian nama="password_confirmation" label="Ulangi kata sandi baru" jenis="password" wajib/>
            <button class="btn btn-primary" type="submit">Ganti kata sandi</button>
        </form>
        <div class="kartu">
            <h2 class="kartu-judul">Hak aksesku</h2>
            <p class="teks-2 small">Role: {{ collect($user->roleSlugs())->map(fn ($r) => ['super_admin' => 'Super Admin', 'manager' => 'Manager', 'member' => 'Member'][$r] ?? $r)->implode(', ') ?: '-' }}</p>
            <ul class="small mb-0" style="columns:2">@foreach ($izin as $p)<li>{{ $p->name }}</li>@endforeach</ul>
        </div>
    </div>
</div>
