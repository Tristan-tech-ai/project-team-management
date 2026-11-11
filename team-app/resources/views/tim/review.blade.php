@php use App\Support\Waktu; @endphp
<x-layouts.tim judul="Review Task">
    <div class="kepala-halaman"><div><h1 class="mb-0">Review Task</h1><p>Terima hasil, atau kembalikan untuk revisi dengan alasan.</p></div></div>
    <div class="daftar-task">
        @forelse ($menunggu as $s)
            <div class="kartu">
                <a class="fw-semibold" href="{{ route('tasks.show', $s->task) }}">{{ $s->task->title }}</a>
                <div class="small teks-2">{{ $s->task->project?->name }} · {{ $s->submitter?->name }} · {{ Waktu::tanggalJam($s->submitted_at) }}</div>
                @include('bersama.isi-submission', ['s' => $s])
                <form method="post" action="{{ route('submissions.review', $s) }}" class="mt-2">@csrf<input type="hidden" name="keputusan" value="approved"><button class="btn btn-success w-100" type="submit"><x-ikon name="circle-check"/> Terima</button></form>
                <form method="post" action="{{ route('submissions.review', $s) }}" class="mt-2">@csrf<input type="hidden" name="keputusan" value="revision">
                    <label class="form-label small" for="r{{ $s->id }}">Alasan revisi</label><textarea id="r{{ $s->id }}" name="review_note" rows="2" class="form-control mb-2" required minlength="5"></textarea>
                    <button class="btn btn-outline-danger w-100" type="submit">Kembalikan untuk revisi</button></form>
            </div>
        @empty
            <div class="kartu"><p class="mb-0 teks-2">Tidak ada hasil yang menunggu review-mu.</p></div>
        @endforelse
    </div>
</x-layouts.tim>
