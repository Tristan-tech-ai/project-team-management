<x-layouts.guest title="Masuk">
    @php($isTeamApp = ! \Illuminate\Support\Facades\Route::has('management.index'))
    @php($demo = \App\Support\DemoAccounts::forLogin(withSuperAdmin: ! $isTeamApp))
    <x-slot:aside>
        <div class="guest-aside-inner">
            <p class="guest-eyebrow">{{ $isTeamApp ? 'Team App' : 'Admin App' }}</p>
            <p class="guest-headline">{{ $isTeamApp ? 'Kerjakan, centang, dan kirim hasil dari mana saja.' : 'Project, task, dan laporan tim agensi di satu tempat.' }}</p>
            <ul class="guest-points">
                @if($isTeamApp)
                    <li><x-icon name="square-check"/><span>Lihat task hari ini dan yang terlambat begitu masuk.</span></li>
                    <li><x-icon name="send"/><span>Kirim tautan atau berkas hasil, langsung masuk review.</span></li>
                    <li><x-icon name="calendar-days"/><span>Kalender deadline, rapat, dan jadwal unggah konten.</span></li>
                @else
                    <li><x-icon name="copy"/><span>Buat project dari template, semua task dan checklist ikut jadi.</span></li>
                    <li><x-icon name="square-kanban"/><span>Papan Kanban dengan review hasil dan persetujuan deadline.</span></li>
                    <li><x-icon name="chart-no-axes-combined"/><span>Pantau keterlambatan dan beban tim tanpa rekap manual.</span></li>
                @endif
            </ul>
            {{-- The real status flow of a task, so the panel explains how work moves instead of showing a placeholder picture. --}}
            @php($flow = $isTeamApp
                ? [['Antrian', 'Task baru masuk ke Task Saya.'], ['Dikerjakan', 'Centang checklist pertama, status ikut berubah.'], ['Review', 'Kirim tautan atau berkas hasil.'], ['Selesai', 'Manager menerima, atau mengembalikan dengan catatan revisi.']]
                : [['Antrian', 'Task dibuat dari template, lengkap dengan checklist dan deadline.'], ['Dikerjakan', 'Anggota mencentang checklist.'], ['Review', 'Hasil masuk lewat tautan atau berkas.'], ['Selesai', 'Manager menerima; tepat waktu ikut terhitung di KPI.']])
            <div class="guest-flow">
                <p class="guest-flow-title">{{ $isTeamApp ? 'Perjalanan task-mu' : 'Alur satu task' }}</p>
                <ol class="guest-flow-steps">
                    @foreach($flow as [$status, $text])
                        <li><span class="guest-flow-dot" aria-hidden="true">{{ $loop->iteration }}</span><span><strong>{{ $status }}</strong><span class="d-block">{{ $text }}</span></span></li>
                    @endforeach
                </ol>
            </div>
        </div>
    </x-slot:aside>
    <h1 class="h3 mb-1">Masuk ke {{ $isTeamApp ? 'Team App' : 'Admin App' }}</h1>
    <p class="muted mb-4">{{ $isTeamApp ? 'Kerjakan dan laporkan tugasmu.' : 'Kelola project, tim, dan laporan.' }}</p>
    <form method="post" action="{{ route('login') }}" novalidate data-login-form>
        @csrf
        <x-field name="email" label="Email" type="email" required/>
        <x-field name="password" label="Kata sandi" type="password" required/>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
            <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Masuk</button>
    </form>
    @if($demo)
        <section class="demo-login mt-4" aria-labelledby="demo-login-title">
            <h2 id="demo-login-title" class="demo-login-title">Coba sebagai</h2>
            <p class="muted small mb-2">Akun demo di komputer ini. Klik untuk mengisi email dan sandi lalu masuk.</p>
            <div class="demo-login-list">
                @foreach($demo as $account)
                    <button type="button" class="demo-login-item" data-demo-email="{{ $account['email'] }}" data-demo-password="{{ $account['password'] }}">
                        <span class="avatar" aria-hidden="true">{{ \Illuminate\Support\Str::of($account['name'])->explode(' ')->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                        <span class="min-w-0 text-start"><span class="d-block fw-semibold">{{ $account['role'] }}</span><span class="d-block muted small text-truncate">{{ $account['email'] }}</span></span>
                        <x-icon name="arrow-right" class="ms-auto"/>
                    </button>
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.guest>
