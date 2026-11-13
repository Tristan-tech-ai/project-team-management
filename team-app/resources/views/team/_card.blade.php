{{-- Phone task card (prototype 7.4): project, title, subtasks, people, progress, deadline, status. --}}
@php
    use App\Support\Codes;
    use App\Support\Dates;
    $late = $t->status !== 'done' && $t->due_at && $t->due_at->isPast();
    $total = (int) ($t->sub_total ?? 0);
    $done = (int) ($t->sub_done ?? 0);
    $people = $t->relationLoaded('assignees') ? $t->assignees->count() : null;
@endphp
<a href="{{ route('tasks.show', $t) }}" class="panel d-block text-decoration-none text-reset">
    <div class="d-flex justify-content-between gap-2 align-items-start">
        <div class="small muted text-truncate">{{ $t->project?->name }}</div>
        <x-badge :code="$late ? 'late' : $t->status" :text="$late ? 'Terlambat' : Codes::label(Codes::TASK_STATUS, $t->status)"/>
    </div>
    <div class="fw-semibold fs-6 mt-1" style="line-height:1.3">{{ $t->title }}</div>
    <div class="small muted mt-1">{{ $total ? "{$done} / {$total} subtask" : 'Tanpa checklist' }} @if($people !== null) · {{ $people }} orang @endif</div>
    @if($total)<div class="meter mt-2"><span style="width:{{ round($done * 100 / $total) }}%"></span></div>@endif
    <div class="small mt-2 {{ $late ? 'text-danger fw-semibold' : 'muted' }}">Deadline: {{ Dates::date($t->due_at) }}</div>
</a>
