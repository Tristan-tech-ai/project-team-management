{{-- U5 quick add: a small button beside a select that creates the missing record in a dialog and selects it, without leaving the form.
     Only rendered for people allowed to create that record; the server checks the permission again. --}}
@props(['url', 'target', 'title', 'label', 'linkCompany' => null])
<button type="button" class="quick-add-link" data-quick-add data-url="{{ $url }}" data-target="{{ $target }}" data-title="{{ $title }}" @if($linkCompany) data-link-company="{{ $linkCompany }}" @endif><x-icon name="plus" :size="16"/>{{ $label }}</button>
