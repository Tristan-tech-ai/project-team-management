@props(['name', 'size' => 18])
@php
    $file = resource_path('icons/'.preg_replace('/[^a-z0-9-]/', '', (string) $name).'.svg');
    $svg = is_file($file) ? file_get_contents($file) : '';
    $svg = preg_replace('/\s(width|height)="24"/', '', $svg);
    $cssClass = trim('icon '.$attributes->get('class'));
@endphp
@if($svg !== '')
{!! preg_replace('/^<svg /', '<svg aria-hidden="true" focusable="false" width="'.(int) $size.'" height="'.(int) $size.'" class="'.e($cssClass).'" ', $svg) !!}
@endif
