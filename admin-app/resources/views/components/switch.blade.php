{{-- On/off switch (U4) with a hidden 0 so an unchecked switch is still sent. New records start switched on. --}}
@props(['name' => 'is_active', 'label' => 'Aktif', 'checked' => true, 'help' => null])
@php($id = 'sw-'.str_replace(['[', ']', '.'], '-', $name))
<div {{ $attributes->merge(['class' => 'form-check form-switch switch-field mb-3']) }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <input class="form-check-input" type="checkbox" role="switch" id="{{ $id }}" name="{{ $name }}" value="1" @checked(old($name, $checked)) @if($help) aria-describedby="{{ $id }}-b" @endif>
    <label class="form-check-label" for="{{ $id }}">{{ $label }}</label>
    @if($help)<span id="{{ $id }}-b" class="form-text ms-1">{{ $help }}</span>@endif
</div>
