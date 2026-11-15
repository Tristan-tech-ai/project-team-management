{{-- I16 term explanation in place: a small question-mark button that shows one sentence (click, tap, or keyboard focus). --}}
@props(['term', 'text'])
<button type="button" {{ $attributes->merge(['class' => 'hint-button']) }} data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"
        data-bs-content="{{ $text }}" aria-label="Apa itu {{ $term }}?"><x-icon name="circle-help" :size="16"/></button>
