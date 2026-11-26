@if($s->submission_note)<p class="small mb-1 mt-1" style="white-space:pre-line">{{ $s->submission_note }}</p>@endif
@if(! $s->submission_note && $s->attachments->isEmpty())<p class="small muted mb-0 mt-1">Dikirim tanpa tautan, berkas, atau catatan.</p>@endif
<ul class="list-unstyled small mb-0">
    @foreach($s->attachments as $l)
        <li>@if($l->external_url)
                <x-icon name="link" :size="14"/> <a href="{{ $l->external_url }}" target="_blank" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit($l->external_url, 70) }}</a>
            @else
                <x-icon name="paperclip" :size="14"/> <a href="{{ route('attachments.show', $l) }}">{{ $l->original_name }}</a> <span class="muted">({{ number_format(($l->file_size_bytes ?? 0) / 1024, 0, ',', '.') }} KB)</span>
            @endif</li>
    @endforeach
</ul>
