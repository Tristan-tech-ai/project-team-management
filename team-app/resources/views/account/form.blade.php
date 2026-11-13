<div class="row g-3">
    <div class="col-lg-6">
        <form method="post" action="{{ route('account.update') }}" enctype="multipart/form-data" class="panel h-100">
            @csrf @method('PUT')
            <h2 class="panel-title">Profil</h2>
            <div class="d-flex align-items-center gap-3 mb-3">
                @if($user->profile?->avatar_path)
                    <img src="{{ route('account.photo', ['user' => $user->id, 'v' => substr(md5($user->profile->avatar_path), 0, 8)]) }}" alt="Foto {{ $user->name }}" width="64" height="64" class="rounded-circle object-fit-cover">
                @else
                    <span class="rounded-circle d-grid fw-bold" style="width:64px;height:64px;place-items:center;background:var(--ptm-action-soft);color:var(--ptm-action-dark)" aria-hidden="true">{{ $user->initials() }}</span>
                @endif
                <div class="muted small">{{ $user->email }}</div>
            </div>
            <x-field name="name" label="Nama" :value="$user->name" required/>
            <x-field name="phone" label="Telepon" :value="$user->profile?->phone"/>
            <div class="mb-3"><label class="form-label" for="photo">Foto</label>
                <input class="form-control @error('photo') is-invalid @enderror" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="photo-b">
                <div id="photo-b" class="form-text">JPG, PNG, atau WebP, paling besar 2 MB.</div>
                @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
            <button class="btn btn-primary" type="submit">Simpan profil</button>
        </form>
    </div>
    <div class="col-lg-6">
        <form method="post" action="{{ route('account.password') }}" class="panel mb-3">
            @csrf @method('PUT')
            <h2 class="panel-title">Ganti kata sandi</h2>
            <x-field name="current_password" label="Kata sandi sekarang" type="password" required/>
            <x-field name="password" label="Kata sandi baru" type="password" required help="Minimal 12 karakter dengan huruf dan angka."/>
            <x-field name="password_confirmation" label="Ulangi kata sandi baru" type="password" required/>
            <button class="btn btn-primary" type="submit">Ganti kata sandi</button>
        </form>
        <div class="panel">
            <h2 class="panel-title">Hak aksesku</h2>
            <p class="muted small">Role: {{ collect($user->roleSlugs())->map(fn ($r) => ['super_admin' => 'Super Admin', 'manager' => 'Manager', 'member' => 'Member'][$r] ?? $r)->implode(', ') ?: '-' }}</p>
            <ul class="small mb-0" style="columns:2">@foreach($permissions as $p)<li>{{ $p->name }}</li>@endforeach</ul>
        </div>
    </div>
</div>
