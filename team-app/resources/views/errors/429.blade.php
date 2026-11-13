<x-layouts.guest title="Terlalu banyak permintaan">
    <p class="muted mb-1">Kode 429</p>
    <h1 class="h3">Terlalu banyak permintaan</h1>
    <p>Tunggu sebentar lalu coba lagi.</p>
    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="{{ url('/') }}">Ke beranda</a><a class="btn btn-outline-secondary" href="javascript:history.back()">Kembali</a></div>
</x-layouts.guest>
