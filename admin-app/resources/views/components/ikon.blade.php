@props(['name', 'size' => 18])
@php
    $berkas = resource_path('ikon/'.preg_replace('/[^a-z0-9-]/', '', (string) $name).'.svg');
    $svg = is_file($berkas) ? file_get_contents($berkas) : '';
    $svg = preg_replace('/\s(width|height)="24"/', '', $svg);
    $kelas = trim('ikon '.$attributes->get('class'));
@endphp
@if ($svg !== '')
{!! preg_replace('/^<svg /', '<svg aria-hidden="true" focusable="false" width="'.(int) $size.'" height="'.(int) $size.'" class="'.e($kelas).'" ', $svg) !!}
@endif
