<x-layouts.tamu judul="Masuk">
    <h1 class="h3 mb-1">Masuk ke {{ config('app.name') }}</h1>
    <p class="teks-2 mb-4">{{ str_contains(config('app.name'), 'Team') ? 'Kerjakan dan laporkan tugasmu.' : 'Kelola project, tim, dan laporan.' }}</p>
    <form method="post" action="{{ route('login') }}" novalidate>
        @csrf
        <x-isian nama="email" label="Email" jenis="email" wajib/>
        <x-isian nama="password" label="Kata sandi" jenis="password" wajib/>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="ingat" id="ingat" value="1">
            <label class="form-check-label" for="ingat">Ingat saya di perangkat ini</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Masuk</button>
    </form>
</x-layouts.tamu>
