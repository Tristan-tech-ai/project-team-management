<x-layouts.guest title="Masuk">
    <h1 class="h3 mb-1">Masuk ke {{ config('app.name') }}</h1>
    <p class="muted mb-4">{{ str_contains(config('app.name'), 'Team') ? 'Kerjakan dan laporkan tugasmu.' : 'Kelola project, tim, dan laporan.' }}</p>
    <form method="post" action="{{ route('login') }}" novalidate>
        @csrf
        <x-field name="email" label="Email" type="email" required/>
        <x-field name="password" label="Kata sandi" type="password" required/>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Masuk</button>
    </form>
</x-layouts.guest>
