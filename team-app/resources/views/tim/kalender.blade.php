<x-layouts.tim judul="Kalender Saya">
    <div class="kepala-halaman"><div><h1 class="mb-0">Kalender Saya</h1><p>Deadline, rapat, jadwal publikasi, dan task yang selesai.</p></div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('activities.index') }}"><x-ikon name="activity"/> Aktivitas saya</a></div>
    @include('bersama.kalender', ['saya' => true])
</x-layouts.tim>
