{{-- Four-column Kanban. Desktop: drag (SortableJS). Phone: status dropdown on each card. Same endpoint: PATCH /tasks/{id}/status. --}}
@php($columnLabels = \App\Support\Codes::TASK_STATUS)
@php($boardPageSize = 50)
<div class="kanban" data-kanban data-refresh-region="board">
    @foreach($columnLabels as $code => $label)
        @php($cards = $columns[$code] ?? collect())
        <section class="kanban-column" aria-labelledby="col-{{ $code }}">
            <header class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="mb-0" id="col-{{ $code }}">{{ $label }}</h2>
                <span class="pill pill-plain pill-neutral numeric" data-count="{{ $code }}">{{ $cards->count() }}</span>
            </header>
            <div class="kanban-body" data-kanban-column data-status="{{ $code }}" data-keep-scroll="{{ $code }}">
                {{-- Dense boards: the first 50 cards show, the rest wait behind "Tampilkan 50 lagi" (still in the page, so counts and moves stay right). --}}
                @foreach($cards as $t)
                    @include('shared.task-card', ['t' => $t, 'later' => $loop->index >= $boardPageSize])
                @endforeach
                <p class="kanban-empty small muted mb-0" @if($cards->isNotEmpty()) hidden @endif>Belum ada task di sini.</p>
            </div>
            @if($cards->count() > $boardPageSize)
                <button type="button" class="btn btn-sm btn-outline-secondary kanban-more" data-kanban-more>Tampilkan <span data-more-next>{{ min($boardPageSize, $cards->count() - $boardPageSize) }}</span> lagi <span class="muted">(<span data-more-left>{{ $cards->count() - $boardPageSize }}</span> tersisa)</span></button>
            @endif
        </section>
    @endforeach
</div>
