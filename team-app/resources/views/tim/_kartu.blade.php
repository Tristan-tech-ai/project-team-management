{{-- Kartu task HP (prototype 7.4): project, judul, subtask, orang, progres, deadline, status. --}}
@php
    use App\Support\Kode;
    use App\Support\Waktu;
    $telat = $t->status !== 'done' && $t->due_at && $t->due_at->isPast();
    $total = (int) ($t->sub_total ?? 0);
    $selesai = (int) ($t->sub_selesai ?? 0);
    $orang = $t->relationLoaded('assignees') ? $t->assignees->count() : null;
@endphp
<a href="{{ route('tasks.show', $t) }}" class="kartu d-block text-decoration-none text-reset">
    <div class="d-flex justify-content-between gap-2 align-items-start">
        <div class="small teks-2 text-truncate">{{ $t->project?->name }}</div>
        <x-lencana :kode="$telat ? 'telat' : $t->status" :teks="$telat ? 'Terlambat' : Kode::label(Kode::TASK_STATUS, $t->status)"/>
    </div>
    <div class="fw-semibold fs-6 mt-1" style="line-height:1.3">{{ $t->title }}</div>
    <div class="small teks-2 mt-1">{{ $selesai }} / {{ $total }} subtask @if($orang !== null) · {{ $orang }} orang @endif</div>
    @if ($total)<div class="progres mt-2"><span style="width:{{ round($selesai * 100 / $total) }}%"></span></div>@endif
    <div class="small mt-2 {{ $telat ? 'text-danger fw-semibold' : 'teks-2' }}">Deadline: {{ Waktu::tanggal($t->due_at) }}</div>
</a>
