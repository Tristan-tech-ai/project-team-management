{{-- Phone task card (prototype 7.4): project, title, subtasks, people, progress, deadline, status.
     The whole card opens the task; the next step (Mulai, Kirim hasil) is a button on the card itself (I2). --}}
@php
    use App\Support\Codes;
    use App\Support\Dates;
    $late = $t->status !== 'done' && $t->due_at && $t->due_at->isPast();
    $total = (int) ($t->sub_total ?? 0);
    $done = (int) ($t->sub_done ?? 0);
    $people = $t->relationLoaded('assignees') ? $t->assignees->count() : null;
    $user = auth()->user();
    $canStart = $t->status === 'backlog' && $user->can('moveStatus', $t);
    $canSend = $t->status === 'in_progress' && $user->can('submit', $t);
@endphp
<article class="panel task-tile">
    <div class="d-flex justify-content-between gap-2 align-items-start">
        <div class="small muted text-truncate min-w-0">{{ $t->project?->name }}</div>
        <x-badge :code="$late ? 'late' : $t->status" :text="$late ? 'Terlambat' : Codes::label(Codes::TASK_STATUS, $t->status)"/>
    </div>
    <a href="{{ route('tasks.show', $t) }}" class="task-tile-title stretched-link" data-task-panel>{{ $t->title }}</a>
    <div class="small muted mt-1">{{ $total ? "{$done} / {$total} subtask" : 'Tanpa checklist' }} @if($people !== null) · {{ $people }} orang @endif</div>
    @if($total)<div class="meter mt-2"><span style="width:{{ round($done * 100 / $total) }}%"></span></div>@endif
    <div class="small mt-2 {{ $late ? 'text-danger fw-semibold' : 'muted' }}">Deadline: {{ Dates::date($t->due_at) }}@if($t->due_at && $t->status !== 'done') · {{ Dates::relative($t->due_at) }}@endif</div>
    @if($canStart)
        <form class="task-tile-action" method="post" action="{{ route('tasks.status', $t) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="in_progress">
            <button class="btn btn-outline-primary btn-sm w-100" type="submit"><x-icon name="send" :size="16"/> Mulai</button></form>
    @elseif($canSend)
        <div class="task-tile-action"><a class="btn btn-outline-primary btn-sm w-100" href="{{ route('tasks.show', $t) }}#submit-result" data-task-panel><x-icon name="send" :size="16"/> Kirim hasil</a></div>
    @endif
</article>
