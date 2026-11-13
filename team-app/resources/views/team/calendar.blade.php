<x-layouts.team title="Kalender Saya">
    <div class="page-header"><div><h1 class="mb-0">Kalender Saya</h1><p>Deadline, rapat, jadwal publikasi, dan task yang selesai.</p></div>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('activities.index') }}"><x-icon name="activity"/> Aktivitas saya</a></div>
    @include('shared.calendar', ['mine' => true])
</x-layouts.team>
