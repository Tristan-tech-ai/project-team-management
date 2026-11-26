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
    $routine = $subTotal > 0 && (int) ($t->sub_dated ?? 0) === $subTotal; // daily routine: one dated checklist item per day
    $canMoveStatus = $user->can('moveStatus', $t) || $user->can('review', $t);
    $people = $t->relationLoaded('assignees') ? $t->assignees : collect();
    $others = $people->reject(fn ($p) => (int) $p->id === (int) $t->pic_user_id); // the PIC already shows first on the card
    $initials = fn ($name) => \Illuminate\Support\Str::of($name)->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
@endphp
<article class="task-card" data-task-id="{{ $t->id }}" data-status="{{ $t->status }}" @if($canMoveStatus) data-movable @endif @if(! empty($later)) hidden data-kanban-later @endif aria-label="{{ $t->title }}">
    {{-- Compact card (Linear/Railway boards): title, one meta line, the next step as a small button on the last line. --}}
    <div class="task-card-head">
        <div class="flex-grow-1 min-w-0">
            @if(! empty($showProject))<div class="task-card-project text-truncate">{{ $t->project?->name }}</div>@endif
            <a href="{{ route('tasks.show', $t) }}" class="task-title no-stretch" data-task-panel draggable="false">{{ $t->title }}</a>
        </div>
        @if($t->priority === 'high' || $t->priority === 'urgent')<span class="pill pill-risk">{{ Codes::label(Codes::PRIORITY, $t->priority) }}</span>@endif
    </div>
    @php($hasAction = ($t->status === 'backlog' && $user->can('moveStatus', $t)) || ($t->status === 'in_progress' && $user->can('submit', $t)) || ($t->status === 'review' && $user->can('review', $t)))
    {{-- One line: PIC as initials, checklist count, time to the deadline, people, then the next step on the right. Full words stay in titles and for screen readers. --}}
    <div class="task-card-meta">
        <span class="avatar avatar-pic" title="PIC: {{ $t->pic?->name ?? '-' }}" aria-hidden="true">{{ $t->pic ? $initials($t->pic->name) : '-' }}</span><span class="visually-hidden">PIC: {{ $t->pic?->name ?? '-' }}</span>
        @if($subTotal)<span class="meta-sub numeric" title="Checklist {{ $subDone }} dari {{ $subTotal }} {{ $routine ? 'hari' : 'subtask' }}"><x-icon name="list-checks" :size="13"/><span data-sub-count>{{ $subDone }} / {{ $subTotal }} {{ $routine ? 'hari' : 'subtask' }}</span></span>@endif
        @if($t->due_at)<span class="meta-due numeric {{ $late ? 'is-late' : '' }}" title="Deadline: {{ Dates::date($t->due_at) }}"><x-icon name="calendar-days" :size="13"/>{{ $t->status !== 'done' ? Dates::relative($t->due_at) : Dates::date($t->due_at) }}<span class="visually-hidden"> (Deadline: {{ Dates::date($t->due_at) }})</span></span>@endif
        @if($late)<span class="pill pill-late">Terlambat</span>@endif
        @if(($t->pending ?? 0) > 0 || $t->status === 'review')<span class="pill pill-review">Menunggu review</span>@endif
        @if($others->isNotEmpty())
            <span class="avatar-stack" title="Pelaksana: {{ $people->pluck('name')->implode(', ') }}">
                @foreach($others->take(3) as $person)<span class="avatar" aria-hidden="true">{{ $initials($person->name) }}</span>@endforeach
                @if($others->count() > 3)<span class="avatar avatar-more" aria-hidden="true">+{{ $others->count() - 3 }}</span>@endif
                <span class="visually-hidden">Pelaksana: {{ $people->pluck('name')->implode(', ') }}</span>
            </span>
        @endif
        {{-- Phones: the status dropdown below replaces this button, so the card stays one line (PKL supervisor: lebih minimalis). --}}
        @if($t->status === 'backlog' && $user->can('moveStatus', $t))
            <form class="card-actions d-none d-lg-flex" method="post" action="{{ route('tasks.status', $t) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="in_progress">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><x-icon name="send" :size="14"/> Mulai</button></form>
        @elseif($t->status === 'in_progress' && $user->can('submit', $t))
            <div class="card-actions d-none d-lg-flex"><a class="btn btn-sm btn-outline-secondary" href="{{ route('tasks.show', $t) }}#submit-result" data-task-panel><x-icon name="send" :size="14"/> Kirim hasil</a></div>
        @elseif($t->status === 'review' && $user->can('review', $t))
            <div class="card-actions d-none d-lg-flex"><a class="btn btn-sm btn-primary" href="{{ route('tasks.show', $t) }}#review" data-task-panel><x-icon name="clipboard-check" :size="14"/> Review hasil</a></div>
        @endif
        {{-- Touch screens: one small status dropdown at the end of the line (Technical Guide 8.2: dropdown status on mobile). --}}
        @if($canMoveStatus)
            <span class="card-status d-lg-none">
                <label class="visually-hidden" for="st-{{ $t->id }}">Status {{ $t->title }}</label>
                <select id="st-{{ $t->id }}" class="form-select form-select-sm" data-change-status="{{ route('tasks.status', $t) }}" data-result-url="{{ route('tasks.show', $t) }}#submit-result">
                    @foreach(Codes::TASK_STATUS as $k => $v)<option value="{{ $k }}" @selected($t->status === $k)>{{ $v }}</option>@endforeach
                </select>
            </span>
        @endif
    </div>
</article>
