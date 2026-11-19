{{-- One checklist item with its tick box (ticked through resources/js/kanban.js initChecklist; works as a form without JavaScript). --}}
<li class="d-flex align-items-center gap-2 py-1 {{ $class ?? '' }}">
    <form method="post" action="{{ route('subtasks.toggle', $s) }}" class="d-flex align-items-center gap-2 m-0">@csrf @method('PATCH')
        <input type="hidden" name="done" value="{{ $s->status === 'done' ? 0 : 1 }}">
        <input class="form-check-input mt-0" type="checkbox" id="s-{{ $s->id }}" data-check-subtask="{{ route('subtasks.toggle', $s) }}" @checked($s->status === 'done') @disabled(! $canTick)>
        <label for="s-{{ $s->id }}" class="{{ $s->status === 'done' ? 'text-decoration-line-through muted' : '' }}">{{ $s->title }}</label>
        <noscript><button class="btn btn-sm btn-outline-secondary" type="submit">{{ $s->status === 'done' ? 'Buka' : 'Selesai' }}</button></noscript>
    </form>
</li>
