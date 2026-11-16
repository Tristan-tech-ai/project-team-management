@php use App\Support\Dates; @endphp
<x-layouts.team title="Review Task">
    <div class="page-header"><div><h1 class="mb-0">Review Task</h1><p>Terima hasil, atau kembalikan untuk revisi dengan alasan.</p></div></div>
    <div class="task-list" data-refresh-region="review-queue">
        @forelse($pending as $s)
            <div class="panel">
                <a class="fw-semibold" href="{{ route('tasks.show', $s->task) }}" data-task-panel>{{ $s->task->title }}</a>
                <div class="small muted">{{ $s->task->project?->name }} · {{ $s->submitter?->name }} · {{ Dates::dateTime($s->submitted_at) }}</div>
                @include('shared.submission-content', ['s' => $s])
                <form method="post" action="{{ route('submissions.review', $s) }}" class="mt-2">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-success w-100" type="submit"><x-icon name="circle-check"/> Terima</button></form>
                <form method="post" action="{{ route('submissions.review', $s) }}" class="mt-2">@csrf<input type="hidden" name="decision" value="revision">
                    <label class="form-label small" for="r{{ $s->id }}">Alasan revisi</label><textarea id="r{{ $s->id }}" name="review_note" rows="2" class="form-control mb-2" required minlength="5"></textarea>
                    <button class="btn btn-outline-danger w-100" type="submit">Kembalikan untuk revisi</button></form>
            </div>
        @empty
            <div class="panel"><p class="mb-0 muted">Tidak ada hasil yang menunggu review-mu.</p></div>
        @endforelse
    </div>
</x-layouts.team>
