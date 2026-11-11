{{-- Kartu task: judul, PIC, anggota, progres subtask, deadline, prioritas, label Terlambat (Petunjuk Teknis 8.2). --}}
@php
    use App\Support\Kode;
    use App\Support\Waktu;
    $telat = $t->status !== 'done' && $t->due_at && $t->due_at->isPast();
    $subTotal = (int) ($t->sub_total ?? 0);
    $subSelesai = (int) ($t->sub_selesai ?? 0);
    $persen = $subTotal ? (int) round($subSelesai * 100 / $subTotal) : 0;
    $bolehPindah = auth()->user()->can('moveStatus', $t) || auth()->user()->can('review', $t);
@endphp
<article class="kartu-task" data-task-id="{{ $t->id }}" data-status="{{ $t->status }}" aria-label="{{ $t->title }}">
    <div class="d-flex align-items-start gap-2">
        @if ($bolehPindah)<span class="task-handle d-none d-lg-inline-flex" title="Seret untuk memindah kolom" aria-hidden="true"><x-ikon name="grip-vertical" :size="16"/></span>@endif
        <div class="flex-grow-1 min-w-0">
            @if (! empty($tampilProject))<div class="small teks-2 text-truncate">{{ $t->project?->name }}</div>@endif
            <a href="{{ route('tasks.show', $t) }}" class="judul-task stretched-link-tidak">{{ $t->title }}</a>
        </div>
        @if ($t->priority === 'high' || $t->priority === 'urgent')<span class="lencana lencana-risk">{{ Kode::label(Kode::PRIORITY, $t->priority) }}</span>@endif
    </div>
    <div class="small teks-2 mt-2">PIC: {{ $t->pic?->name ?? '-' }}</div>
    <div class="small teks-2">Anggota: {{ $t->relationLoaded('assignees') ? ($t->assignees->pluck('name')->implode(', ') ?: '-') : '-' }}</div>
    @if ($subTotal)
        <div class="d-flex align-items-center gap-2 mt-2"><div class="progres flex-grow-1"><span style="width:{{ $persen }}%"></span></div><span class="small angka teks-2" data-sub-jumlah>{{ $subSelesai }} / {{ $subTotal }} subtask</span></div>
    @endif
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <span class="small {{ $telat ? 'text-danger fw-semibold' : 'teks-2' }}">Deadline: {{ Waktu::tanggal($t->due_at) }}</span>
        @if ($telat)<span class="lencana lencana-telat">Terlambat</span>@endif
        @if (($t->menunggu ?? 0) > 0 || $t->status === 'review')<span class="lencana lencana-review">Menunggu review</span>@endif
    </div>
    @if ($bolehPindah)
        <div class="mt-2 d-lg-none">
            <label class="visually-hidden" for="st-{{ $t->id }}">Status {{ $t->title }}</label>
            <select id="st-{{ $t->id }}" class="form-select form-select-sm" data-ubah-status="{{ route('tasks.status', $t) }}">
                @foreach (Kode::TASK_STATUS as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
    @endif
</article>
