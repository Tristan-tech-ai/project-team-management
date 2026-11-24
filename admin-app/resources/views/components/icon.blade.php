@props(['name', 'size' => 18])
@php
    // Built pages point at the icon sprite (see iconSprite() in vite.config.js); without a build the drawing is inlined.
    $name = preg_replace('/[^a-z0-9-]/', '', (string) $name);
    if (! app()->bound('ptm.iconSprite')) {
        $manifest = public_path('build/icons.json');
        $spriteFile = is_file($manifest) ? (json_decode((string) file_get_contents($manifest), true)['file'] ?? '') : '';
        app()->instance('ptm.iconSprite', $spriteFile !== '' && is_file(public_path('build/'.$spriteFile)) ? '/build/'.$spriteFile : '');
    }
    $sprite = app('ptm.iconSprite');
    $file = resource_path('icons/'.$name.'.svg');
    $cssClass = trim('icon '.$attributes->get('class'));
@endphp
@if(is_file($file))
@if($sprite !== '')
<svg aria-hidden="true" focusable="false" width="{{ (int) $size }}" height="{{ (int) $size }}" class="{{ $cssClass }}"><use href="{{ $sprite }}#i-{{ $name }}"/></svg>
@else
{!! preg_replace('/^<svg /', '<svg aria-hidden="true" focusable="false" width="'.(int) $size.'" height="'.(int) $size.'" class="'.e($cssClass).'" ', preg_replace('/\s(width|height)="24"/', '', (string) file_get_contents($file))) !!}
@endif
@endif
