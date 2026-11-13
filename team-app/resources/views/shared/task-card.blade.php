{{-- Task card: title, PIC, members, subtask progress, deadline, priority, Overdue label (Technical Guide 8.2). --}}
@php
    use App\Support\Codes;
    use App\Support\Dates;
    $late = $t->status !== 'done' && $t->due_at && $t->due_at->isPast();
    $subTotal = (int) ($t->sub_total ?? 0);
    $subDone = (int) ($t->sub_done ?? 0);
    $percent = $subTotal ? (int) round($subDone * 100 / $subTotal) : 0;
    $canMoveStatus = auth()->user()->can('moveStatus', $t) || auth()->user()->can('review', $t);
@endphp
<article class="task-card" data-task-id="{{ $t->id }}" data-status="{{ $t->status }}" aria-label="{{ $t->title }}">
    <div class="d-flex align-items-start gap-2">
        @if($canMoveStatus)<span class="task-handle d-none d-lg-inline-flex" title="Seret untuk memindah kolom" aria-hidden="true"><x-icon name="grip-vertical" :size="16"/></span>@endif
        <div class="flex-grow-1 min-w-0">
            @if(! empty($showProject))<div class="small muted text-truncate">{{ $t->project?->name }}</div>@endif
            <a href="{{ route('tasks.show', $t) }}" class="task-title no-stretch">{{ $t->title }}</a>
        </div>
        @if($t->priority === 'high' || $t->priority === 'urgent')<span class="pill pill-risk">{{ Codes::label(Codes::PRIORITY, $t->priority) }}</span>@endif
    </div>
    <div class="small muted mt-2">PIC: {{ $t->pic?->name ?? '-' }}</div>
    <div class="small muted">Anggota: {{ $t->relationLoaded('assignees') ? ($t->assignees->pluck('name')->implode(', ') ?: '-') : '-' }}</div>
    @if($subTotal)
        <div class="d-flex align-items-center gap-2 mt-2"><div class="meter flex-grow-1"><span style="width:{{ $percent }}%"></span></div><span class="small numeric muted" data-sub-count>{{ $subDone }} / {{ $subTotal }} subtask</span></div>
    @endif
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <span class="small {{ $late ? 'text-danger fw-semibold' : 'muted' }}">Deadline: {{ Dates::date($t->due_at) }}</span>
        @if($late)<span class="pill pill-late">Terlambat</span>@endif
        @if(($t->pending ?? 0) > 0 || $t->status === 'review')<span class="pill pill-review">Menunggu review</span>@endif
    </div>
    @if($canMoveStatus)
        <div class="mt-2 d-lg-none">
            <label class="visually-hidden" for="st-{{ $t->id }}">Status {{ $t->title }}</label>
            <select id="st-{{ $t->id }}" class="form-select form-select-sm" data-change-status="{{ route('tasks.status', $t) }}">
                @foreach(Codes::TASK_STATUS as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
    @endif
</article>
