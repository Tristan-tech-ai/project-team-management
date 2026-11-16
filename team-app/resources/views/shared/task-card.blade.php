{{-- Task card: title, PIC, members, subtask progress, deadline, priority, Overdue label (Technical Guide 8.2).
     I2: the next step for this task is a button on the card (Mulai, Kirim hasil, Review), so the board needs no detour through the detail page.
     I17: people as initials; I4: deadline also as "3 hari lagi" / "terlambat 2 hari". --}}
@php
    use App\Support\Codes;
    use App\Support\Dates;
    $user = auth()->user();
    $late = $t->status !== 'done' && $t->due_at && $t->due_at->isPast();
    $subTotal = (int) ($t->sub_total ?? 0);
    $subDone = (int) ($t->sub_done ?? 0);
    $percent = $subTotal ? (int) round($subDone * 100 / $subTotal) : 0;
    $canMoveStatus = $user->can('moveStatus', $t) || $user->can('review', $t);
    $people = $t->relationLoaded('assignees') ? $t->assignees : collect();
    $initials = fn ($name) => \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp
<article class="task-card" data-task-id="{{ $t->id }}" data-status="{{ $t->status }}" aria-label="{{ $t->title }}">
    <div class="d-flex align-items-start gap-2">
        @if($canMoveStatus)<span class="task-handle d-none d-lg-inline-flex" title="Seret untuk memindah kolom" aria-hidden="true"><x-icon name="grip-vertical" :size="16"/></span>@endif
        <div class="flex-grow-1 min-w-0">
            @if(! empty($showProject))<div class="small muted text-truncate">{{ $t->project?->name }}</div>@endif
            <a href="{{ route('tasks.show', $t) }}" class="task-title no-stretch" data-task-panel>{{ $t->title }}</a>
        </div>
        @if($t->priority === 'high' || $t->priority === 'urgent')<span class="pill pill-risk">{{ Codes::label(Codes::PRIORITY, $t->priority) }}</span>@endif
    </div>
    <div class="d-flex align-items-center justify-content-between gap-2 mt-2">
        <span class="small muted text-truncate">PIC: {{ $t->pic?->name ?? '-' }}</span>
        @if($people->isNotEmpty())
            <span class="avatar-stack" title="Pelaksana: {{ $people->pluck('name')->implode(', ') }}">
                @foreach($people->take(3) as $person)<span class="avatar" aria-hidden="true">{{ $initials($person->name) }}</span>@endforeach
                @if($people->count() > 3)<span class="avatar avatar-more" aria-hidden="true">+{{ $people->count() - 3 }}</span>@endif
                <span class="visually-hidden">Pelaksana: {{ $people->pluck('name')->implode(', ') }}</span>
            </span>
        @endif
    </div>
    @if($subTotal)
        <div class="d-flex align-items-center gap-2 mt-2"><div class="meter flex-grow-1"><span style="width:{{ $percent }}%"></span></div><span class="small numeric muted" data-sub-count>{{ $subDone }} / {{ $subTotal }} subtask</span></div>
    @endif
    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
        <span class="small {{ $late ? 'text-danger fw-semibold' : 'muted' }}">Deadline: {{ Dates::date($t->due_at) }}@if($t->due_at && $t->status !== 'done') · {{ Dates::relative($t->due_at) }}@endif</span>
        @if($late)<span class="pill pill-late">Terlambat</span>@endif
        @if(($t->pending ?? 0) > 0 || $t->status === 'review')<span class="pill pill-review">Menunggu review</span>@endif
    </div>
    @if($t->status === 'backlog' && $user->can('moveStatus', $t))
        <form class="card-actions" method="post" action="{{ route('tasks.status', $t) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="in_progress">
            <button class="btn btn-sm btn-outline-primary" type="submit"><x-icon name="send" :size="16"/> Mulai</button></form>
    @elseif($t->status === 'in_progress' && $user->can('submit', $t))
        <div class="card-actions"><a class="btn btn-sm btn-outline-primary" href="{{ route('tasks.show', $t) }}#submit-result" data-task-panel><x-icon name="send" :size="16"/> Kirim hasil</a></div>
    @elseif($t->status === 'review' && $user->can('review', $t))
        <div class="card-actions"><a class="btn btn-sm btn-primary" href="{{ route('tasks.show', $t) }}#review" data-task-panel><x-icon name="clipboard-check" :size="16"/> Review hasil</a></div>
    @endif
    @if($canMoveStatus)
        <div class="mt-2 d-lg-none">
            <label class="visually-hidden" for="st-{{ $t->id }}">Status {{ $t->title }}</label>
            <select id="st-{{ $t->id }}" class="form-select form-select-sm" data-change-status="{{ route('tasks.status', $t) }}">
                @foreach(Codes::TASK_STATUS as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach
            </select>
        </div>
    @endif
</article>
