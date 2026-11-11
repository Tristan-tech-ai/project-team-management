{{-- Kalender: Jadwal (deadline + aktivitas terjadwal), Selesai (completed_at), Publikasi, Aktivitas (Petunjuk Teknis 8.6). --}}
<div class="kartu">
    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Mode kalender">
        @foreach (['jadwal' => 'Jadwal', 'aktivitas' => 'Aktivitas', 'selesai' => 'Selesai aktual', 'publikasi' => 'Publikasi'] as $k => $v)
            <button type="button" class="btn btn-sm {{ $loop->first ? 'btn-primary' : 'btn-outline-primary' }}" data-mode-kalender="{{ $k }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $v }}</button>
        @endforeach
    </div>
    <p class="small teks-2" data-ket-mode>Deadline task dan aktivitas terjadwal.</p>
    <div data-kalender="{{ route('calendar.events') }}" data-project="{{ $projectId ?? '' }}" data-saya="{{ ! empty($saya) ? 1 : 0 }}" data-zona="{{ \App\Support\Waktu::zona() }}" style="min-height:560px"></div>
    <ul class="small teks-2 list-inline mt-3 mb-0">
        <li class="list-inline-item"><span class="tanda ev-deadline"></span> Deadline</li>
        <li class="list-inline-item"><span class="tanda ev-telat"></span> Terlambat / menghambat</li>
        <li class="list-inline-item"><span class="tanda ev-aktivitas"></span> Aktivitas</li>
        <li class="list-inline-item"><span class="tanda ev-publikasi"></span> Jadwal publikasi</li>
        <li class="list-inline-item"><span class="tanda ev-selesai"></span> Selesai / terbit</li>
    </ul>
</div>
