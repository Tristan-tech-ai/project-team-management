{{-- I9 command palette (Ctrl+K or Cmd+K): go to any menu the user may open, start a create action, or find a project, task,
     or member. Actions come from the user's own menu and Buat list, so the palette never offers what the role cannot do.
     Opened without a fade, so the first letters typed right after Ctrl+K are not lost. --}}
@props(['menu' => [], 'create' => []])
@php
    $actions = [];
    foreach ($create as [$url, $label, $icon, $hint]) {
        $actions[] = ['label' => 'Buat '.mb_strtolower($label), 'detail' => $hint, 'url' => $url, 'icon' => $icon, 'group' => 'Buat'];
    }
    foreach ($menu as $group) {
        $single = count($group['children'] ?? []) === 1; // shown in the sidebar under the group title (for example Dashboard)
        foreach ($group['children'] ?? [] as $item) {
            $actions[] = ['label' => $single ? $group['title'] : $item['title'], 'detail' => $single ? 'Menu' : $group['title'], 'url' => $item['url'],
                'icon' => ($single ? ($group['icon'] ?? null) : null) ?? $item['icon'] ?? 'circle-dot', 'group' => 'Buka'];
        }
    }
    $actions[] = ['label' => 'Profil & kata sandi', 'detail' => 'Akun', 'url' => route('account.edit'), 'icon' => 'user', 'group' => 'Buka'];
    $icons = collect($actions)->pluck('icon')->push('folder-kanban', 'list-todo', 'users', 'search', 'corner-down-left')->unique();
    $newTask = collect($create)->first(fn ($c) => $c[1] === 'Task')[0] ?? null;
@endphp
<div class="modal command-palette" id="command-palette" tabindex="-1" aria-labelledby="command-palette-title" aria-hidden="true"
    data-command-palette data-search-url="{{ route('search') }}" data-actions="{{ json_encode($actions) }}" data-new-task="{{ $newTask }}">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <h2 class="visually-hidden" id="command-palette-title">Perintah cepat</h2>
            <div class="palette-search">
                <x-icon name="search"/>
                <label class="visually-hidden" for="palette-input">Cari menu, project, task, atau anggota</label>
                <input id="palette-input" class="form-control" type="text" autocomplete="off" spellcheck="false" maxlength="100"
                    placeholder="Ketik menu, project, task, atau nama orang" role="combobox" aria-expanded="true" aria-controls="palette-list" aria-autocomplete="list">
                <kbd class="kbd" aria-hidden="true">Esc</kbd>
            </div>
            <ul class="palette-list" id="palette-list" role="listbox" aria-label="Hasil"></ul>
            <p class="palette-status small muted" role="status" data-palette-status></p>
            <div class="palette-foot small muted">
                <span><kbd class="kbd">↑</kbd><kbd class="kbd">↓</kbd> pilih</span><span><kbd class="kbd">Enter</kbd> buka</span>
                <span><kbd class="kbd">/</kbd> cari</span>@if($newTask)<span><kbd class="kbd">N</kbd> task baru</span>@endif<span><kbd class="kbd">?</kbd> pintasan</span>
            </div>
            {{-- Icons for results, cloned by the script (the icon set is local SVG, not a font). --}}
            <template data-palette-icons>@foreach($icons as $name)<span data-icon="{{ $name }}"><x-icon :name="$name"/></span>@endforeach</template>
        </div>
    </div>
</div>
