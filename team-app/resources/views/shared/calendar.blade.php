{{-- Calendar: Schedule (deadlines + scheduled activities), Done (completed_at), Publication, Activities (Technical Guide 8.6). --}}
<div class="panel">
    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Mode kalender">
        @foreach(['schedule' => 'Jadwal', 'activities' => 'Aktivitas', 'done' => 'Selesai aktual', 'publication' => 'Publikasi'] as $k => $v)
            <button type="button" class="btn btn-sm {{ $loop->first ? 'btn-primary' : 'btn-outline-primary' }}" data-calendar-mode="{{ $k }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $v }}</button>
        @endforeach
    </div>
    <p class="small muted" data-mode-note>Deadline task dan aktivitas terjadwal.</p>
    <div data-calendar="{{ route('calendar.events') }}" data-project="{{ $projectId ?? '' }}" data-mine="{{ ! empty($mine) ? 1 : 0 }}" data-zone="{{ \App\Support\Dates::zone() }}" style="min-height:560px"></div>
    <ul class="small muted list-inline mt-3 mb-0">
        <li class="list-inline-item"><span class="swatch ev-deadline"></span> Deadline</li>
        <li class="list-inline-item"><span class="swatch ev-late"></span> Terlambat / menghambat</li>
        <li class="list-inline-item"><span class="swatch ev-activity"></span> Aktivitas</li>
        <li class="list-inline-item"><span class="swatch ev-publication"></span> Jadwal publikasi</li>
        <li class="list-inline-item"><span class="swatch ev-done"></span> Selesai / terbit</li>
    </ul>
</div>
