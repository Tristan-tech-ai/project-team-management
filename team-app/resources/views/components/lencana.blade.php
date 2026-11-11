@props(['kode', 'teks'])
<span {{ $attributes->merge(['class' => 'lencana lencana-'.preg_replace('/[^a-z_]/', '', (string) $kode)]) }}>{{ $teks }}</span>
