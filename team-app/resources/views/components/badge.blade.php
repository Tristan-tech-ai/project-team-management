@props(['code', 'text'])
<span {{ $attributes->merge(['class' => 'pill pill-'.preg_replace('/[^a-z_]/', '', (string) $code)]) }}>{{ $text }}</span>
