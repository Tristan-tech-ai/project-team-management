@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.admin title="Menunggu Review">
    <div class="page-header"><div><div class="crumb">Tasks</div><h1>Menunggu Review</h1>
        <p>Terima hasil untuk menyelesaikan task, atau kembalikan dengan catatan revisi. Riwayat hasil tidak dihapus.</p></div></div>
    <div class="row g-3">
        @forelse($pending as $s)
            <div class="col-xl-6">
                <div class="panel h-100">
                    <div class="d-flex justify-content-between gap-2"><div><a class="fw-semibold" href="{{ route('tasks.show', $s->task) }}">{{ $s->task->title }}</a>
                        <div class="small muted">{{ $s->task->project?->name }} · dikirim {{ $s->submitter?->name }} {{ Dates::dateTime($s->submitted_at) }}</div></div>
                        <x-badge code="pending" text="Menunggu review"/></div>
                    @include('shared.submission-content', ['s' => $s])
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <form method="post" action="{{ route('submissions.review', $s) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-success btn-sm" type="submit"><x-icon name="circle-check"/> Terima</button></form>
                        <form method="post" action="{{ route('submissions.review', $s) }}" class="d-flex flex-grow-1 gap-2">@csrf<input type="hidden" name="decision" value="revision">
                            <label class="visually-hidden" for="rv{{ $s->id }}">Alasan revisi</label><input id="rv{{ $s->id }}" name="review_note" class="form-control form-control-sm" placeholder="Alasan revisi" required minlength="5">
                            <button class="btn btn-outline-danger btn-sm" type="submit">Revisi</button></form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="panel"><p class="mb-0 muted">Tidak ada hasil yang menunggu review. Hasil baru muncul di sini setelah pelaksana menekan Kirim untuk Review.</p></div></div>
        @endforelse
    </div>
    <div class="panel mt-3">
        <h2 class="panel-title">Riwayat review</h2>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th scope="col">Task</th><th scope="col">Pengirim</th><th scope="col">Peninjau</th><th scope="col">Keputusan</th><th scope="col">Catatan</th><th scope="col">Waktu</th></tr></thead>
            <tbody>@forelse($history as $r)
                <tr><td><a href="{{ route('tasks.show', $r->task_id) }}">{{ $r->task?->title }}</a></td><td>{{ $r->submitter?->name }}</td><td>{{ $r->reviewer?->name }}</td>
                    <td><x-badge :code="$r->status" :text="Codes::label(Codes::SUBMISSION_STATUS, $r->status)"/></td><td class="small">{{ $r->review_note ?? '-' }}</td><td class="small">{{ Dates::dateTime($r->reviewed_at) }}</td></tr>
            @empty<tr><td colspan="6" class="muted">Belum ada review.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</x-layouts.admin>
