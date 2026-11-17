{{-- Simple formatted description (bold, italic, underline, lists): what you see while typing is what is saved and shown.
     The textarea stays the real field and works without JavaScript; resources/js/core.js initRichText shows the editor and
     writes its HTML back into the textarea. The server keeps only the App\Support\RichText allowlist. --}}
@props(['name', 'label', 'value' => null, 'help' => null])
@php
    $id = 'f-'.str_replace(['[', ']', '.'], '-', $name);
    $old = old($name, $value);
    $error = $errors->first($name);
    $tools = [['bold', 'bold', 'Tebal (Ctrl+B)'], ['italic', 'italic', 'Miring (Ctrl+I)'], ['underline', 'underline', 'Garis bawah (Ctrl+U)'],
        ['insertUnorderedList', 'list', 'Daftar berpoin'], ['insertOrderedList', 'list-ordered', 'Daftar bernomor'], ['removeFormat', 'remove-formatting', 'Hapus format']];
@endphp
<div {{ $attributes->merge(['class' => 'mb-3']) }} data-rich-text>
    <label for="{{ $id }}" class="form-label" id="{{ $id }}-label">{{ $label }}</label>
    <div class="rich-text-editor @if($error) is-invalid @endif" data-rich-editor hidden>
        <div class="rich-text-bar" role="toolbar" aria-label="Format {{ strtolower($label) }}" aria-controls="{{ $id }}-editor">
            @foreach($tools as [$command, $icon, $title])
                <button type="button" class="rich-text-tool" data-command="{{ $command }}" title="{{ $title }}" aria-label="{{ $title }}" @unless($command === 'removeFormat') aria-pressed="false" @endunless><x-icon :name="$icon" :size="18"/></button>
                @if($command === 'underline' || $command === 'insertOrderedList')<span class="rich-text-sep" aria-hidden="true"></span>@endif
            @endforeach
        </div>
        <div id="{{ $id }}-editor" class="rich-text rich-text-input" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="{{ $id }}-label"
             @if($help) aria-describedby="{{ $id }}-b" @endif data-placeholder="Tulis deskripsi. Pilih teks lalu tekan B untuk tebal."></div>
    </div>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="4" class="form-control @if($error) is-invalid @endif" data-rich-source
              data-rich-html="{{ \App\Support\RichText::toHtml($old) }}" @if($help) aria-describedby="{{ $id }}-b" @endif>{{ $old }}</textarea>
    @if($help)<div id="{{ $id }}-b" class="form-text">{{ $help }}</div>@endif
    @if($error)<div class="invalid-feedback d-block">{{ $error }}</div>@endif
</div>
