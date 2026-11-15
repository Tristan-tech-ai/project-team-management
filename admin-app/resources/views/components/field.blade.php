{{-- Form field with label, validation error, and old value. type: text|email|password|number|date|datetime-local|textarea|select|url.
     Optional <x-slot:action> sits on the label row, e.g. a quick "+ Tambah client" button (U5); hint adds a term explanation (I16). --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'options' => [], 'required' => false, 'help' => null, 'empty' => null, 'multiple' => false, 'action' => null, 'hint' => null])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $key = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
    $old = old($key, $value);
    $error = $errors->first($key) ?: $errors->first($key.'.*');
@endphp
<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    @php($hasAction = $action !== null && trim((string) $action) !== '')
    @if($hasAction || $hint)<div class="label-row">@endif
    <label for="{{ $id }}" class="form-label">{{ $label }}@if($required) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @if($hint)<x-hint :term="$label" :text="$hint" class="{{ $hasAction ? '' : 'me-auto' }}"/>@endif
    @if($hasAction){{ $action }}@endif
    @if($hasAction || $hint)</div>@endif
    @if($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" rows="3" class="form-control @if($error) is-invalid @endif" @if($required) required @endif @if($help) aria-describedby="{{ $id }}-b" @endif>{{ $old }}</textarea>
    @elseif($type === 'select')
        <select id="{{ $id }}" name="{{ $name }}" class="form-select @if($error) is-invalid @endif" @if($required) required @endif @if($multiple) multiple size="{{ min(6, max(3, count($options))) }}" @endif @if($help) aria-describedby="{{ $id }}-b" @endif>
            @if($empty !== null && ! $multiple)<option value="">{{ $empty }}</option>@endif
            @foreach($options as $k => $v)
                <option value="{{ $k }}" @selected($multiple ? in_array((string) $k, array_map('strval', (array) $old), true) : (string) $old === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ $type === 'password' ? '' : $old }}" class="form-control @if($error) is-invalid @endif" @if($required) required @endif @if($help) aria-describedby="{{ $id }}-b" @endif @if($type === 'password') autocomplete="new-password" @endif>
    @endif
    @if($help)<div id="{{ $id }}-b" class="form-text">{{ $help }}</div>@endif
    @if($error)<div class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
