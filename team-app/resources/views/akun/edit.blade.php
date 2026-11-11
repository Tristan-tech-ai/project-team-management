<x-layouts.tim judul="Profil Saya">
    <div class="kepala-halaman"><div><h1 class="mb-0">Profil Saya</h1></div>
        <form method="post" action="{{ route('logout') }}" class="d-lg-none">@csrf<button class="btn btn-outline-secondary btn-sm" type="submit"><x-ikon name="log-out"/> Keluar</button></form></div>
    @include('akun.isi')
</x-layouts.tim>
