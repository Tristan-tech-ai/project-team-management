<x-layouts.guest title="Halaman ini bukan untuk role-mu">
    <p class="muted mb-1">Kode 403</p>
    <h1 class="h3">Halaman ini bukan untuk role-mu</h1>
    <p>Kamu sudah masuk, tetapi tidak punya izin membuka halaman ini. Minta admin menambahkan izin bila memang perlu.</p>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ url('/') }}">Ke beranda</a><a class="btn btn-outline-secondary" href="javascript:history.back()">Kembali</a></div>
</x-layouts.guest>
