@if ($s->submission_note)<p class="small mb-1 mt-1" style="white-space:pre-line">{{ $s->submission_note }}</p>@endif
<ul class="list-unstyled small mb-0">
    @foreach ($s->attachments as $l)
        <li>@if ($l->external_url)
                <x-ikon name="link" :size="14"/> <a href="{{ $l->external_url }}" target="_blank" rel="noopener nofollow">{{ \Illuminate\Support\Str::limit($l->external_url, 70) }}</a>
            @else
                <x-ikon name="paperclip" :size="14"/> <a href="{{ route('attachments.show', $l) }}">{{ $l->original_name }}</a> <span class="teks-2">({{ number_format(($l->file_size_bytes ?? 0) / 1024, 0, ',', '.') }} KB)</span>
            @endif</li>
    @endforeach
</ul>
