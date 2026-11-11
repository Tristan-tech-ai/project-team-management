{{-- Isian formulir dengan label, galat validasi, dan nilai lama. jenis: text|email|password|number|date|datetime-local|textarea|select|url --}}
@props(['nama', 'label', 'jenis' => 'text', 'nilai' => null, 'pilihan' => [], 'wajib' => false, 'bantuan' => null, 'kosong' => null, 'ganda' => false])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], '-', $nama);
    $kunci = str_replace(['[]', '[', ']'], ['', '.', ''], $nama);
    $lama = old($kunci, $nilai);
    $galat = $errors->first($kunci) ?: $errors->first($kunci.'.*');
@endphp
<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <label for="{{ $id }}" class="form-label">{{ $label }}@if($wajib) <span class="text-danger" aria-hidden="true">*</span>@endif</label>
    @if ($jenis === 'textarea')
        <textarea id="{{ $id }}" name="{{ $nama }}" rows="3" class="form-control @if($galat) is-invalid @endif" @if($wajib) required @endif @if($bantuan) aria-describedby="{{ $id }}-b" @endif>{{ $lama }}</textarea>
    @elseif ($jenis === 'select')
        <select id="{{ $id }}" name="{{ $nama }}" class="form-select @if($galat) is-invalid @endif" @if($wajib) required @endif @if($ganda) multiple size="{{ min(6, max(3, count($pilihan))) }}" @endif @if($bantuan) aria-describedby="{{ $id }}-b" @endif>
            @if ($kosong !== null && ! $ganda)<option value="">{{ $kosong }}</option>@endif
            @foreach ($pilihan as $k => $v)
                <option value="{{ $k }}" @selected($ganda ? in_array((string) $k, array_map('strval', (array) $lama), true) : (string) $lama === (string) $k)>{{ $v }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $id }}" type="{{ $jenis }}" name="{{ $nama }}" value="{{ $jenis === 'password' ? '' : $lama }}" class="form-control @if($galat) is-invalid @endif" @if($wajib) required @endif @if($bantuan) aria-describedby="{{ $id }}-b" @endif @if($jenis === 'password') autocomplete="new-password" @endif>
    @endif
    @if ($bantuan)<div id="{{ $id }}-b" class="form-text">{{ $bantuan }}</div>@endif
    @if ($galat)<div class="invalid-feedback d-block">{{ $galat }}</div>@endif
</div>
