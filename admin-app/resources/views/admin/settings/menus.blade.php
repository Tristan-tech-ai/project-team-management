<x-layouts.admin title="Pengaturan Menu">
    <div class="page-header"><div><div class="crumb">Pengaturan</div><h1>Pengaturan menu</h1>
        <p>Seret <x-icon name="grip-vertical" :size="16"/> untuk mengubah urutan kelompok dan menu, atau pakai panah naik turun. Pindahkan menu ke kelompok lain lewat pilihan Kelompok. Klik ikon untuk menggantinya.</p></div></div>

    <form id="menu-editor" method="post" action="{{ route('settings.menus.update') }}" data-menu-editor>
        @csrf @method('PUT')
        <div class="menu-groups" data-sortable-groups>
            @foreach($groups as $g)
                @php($items = $children[$g->id] ?? collect())
                <section class="menu-group" data-group-id="{{ $g->id }}" aria-label="Kelompok {{ $g->title }}">
                    <input type="hidden" name="menu[{{ $g->id }}][sort_order]" value="{{ $g->sort_order }}" data-order>
                    <div class="menu-row menu-group-head">
                        <span class="drag-handle" data-group-handle title="Seret untuk mengubah urutan kelompok"><x-icon name="grip-vertical"/></span>
                        <button type="button" class="icon-choice" data-icon-choice aria-label="Ganti ikon kelompok {{ $g->title }}"><x-icon :name="$defaultIcon($g)"/></button>
                        <input type="hidden" name="menu[{{ $g->id }}][icon]" value="{{ $defaultIcon($g) }}" data-icon-value>
                        <label class="visually-hidden" for="m{{ $g->id }}t">Nama kelompok</label>
                        <input class="form-control inline-title fw-semibold" id="m{{ $g->id }}t" name="menu[{{ $g->id }}][title]" value="{{ old("menu.$g->id.title", $g->title) }}" required maxlength="120" data-group-title>
                        <span class="muted small text-nowrap" data-item-count>{{ $items->count() }} menu</span>
                        <x-switch name="menu[{{ $g->id }}][is_active]" label="Tampil" :checked="$g->is_active" class="mb-0 ms-auto"/>
                        <div class="move-buttons">
                            <button type="button" class="btn btn-icon" data-move="up" aria-label="Naikkan kelompok {{ $g->title }}"><x-icon name="arrow-up"/></button>
                            <button type="button" class="btn btn-icon" data-move="down" aria-label="Turunkan kelompok {{ $g->title }}"><x-icon name="arrow-down"/></button>
                        </div>
                        @if($items->isEmpty())
                            <button type="submit" form="delete-group-{{ $g->id }}" class="btn btn-icon text-danger" aria-label="Hapus kelompok kosong {{ $g->title }}"><x-icon name="trash-2"/></button>
                        @endif
                    </div>
                    <ol class="menu-items" data-sortable-items data-group-id="{{ $g->id }}">
                        @foreach($items as $c)
                            <li class="menu-row menu-item-row" data-item-id="{{ $c->id }}">
                                <input type="hidden" name="menu[{{ $c->id }}][sort_order]" value="{{ $c->sort_order }}" data-order>
                                <span class="drag-handle" data-item-handle title="Seret untuk memindah menu"><x-icon name="grip-vertical"/></span>
                                <button type="button" class="icon-choice" data-icon-choice aria-label="Ganti ikon menu {{ $c->title }}"><x-icon :name="$defaultIcon($c)"/></button>
                                <input type="hidden" name="menu[{{ $c->id }}][icon]" value="{{ $defaultIcon($c) }}" data-icon-value>
                                <label class="visually-hidden" for="m{{ $c->id }}t">Nama menu</label>
                                <input class="form-control inline-title" id="m{{ $c->id }}t" name="menu[{{ $c->id }}][title]" value="{{ old("menu.$c->id.title", $c->title) }}" required maxlength="120">
                                <div class="menu-field">
                                    <label class="form-label small mb-0" for="m{{ $c->id }}g">Kelompok</label>
                                    <select class="form-select form-select-sm" id="m{{ $c->id }}g" name="menu[{{ $c->id }}][parent_id]" data-move-group>
                                        @foreach($groups as $option)<option value="{{ $option->id }}" @selected($option->id === $c->parent_id)>{{ $option->title }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="menu-field">
                                    <label class="form-label small mb-0" for="m{{ $c->id }}p">Terlihat oleh yang punya izin</label>
                                    <select class="form-select form-select-sm" id="m{{ $c->id }}p" name="menu[{{ $c->id }}][permission_id]">
                                        <option value="">Semua pengguna yang masuk</option>
                                        @foreach($permissions as $id => $name)<option value="{{ $id }}" @selected($c->permission_id === $id)>{{ $name }}</option>@endforeach
                                    </select>
                                </div>
                                <x-switch name="menu[{{ $c->id }}][is_active]" label="Tampil" :checked="$c->is_active" class="mb-0"/>
                                <div class="move-buttons">
                                    <button type="button" class="btn btn-icon" data-move="up" aria-label="Naikkan menu {{ $c->title }}"><x-icon name="arrow-up"/></button>
                                    <button type="button" class="btn btn-icon" data-move="down" aria-label="Turunkan menu {{ $c->title }}"><x-icon name="arrow-down"/></button>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <p class="menu-empty muted small" data-empty-hint @if($items->isNotEmpty()) hidden @endif>Belum ada menu. Seret menu ke sini, atau pilih kelompok ini di baris menu.</p>
                </section>
            @endforeach
        </div>
        <div class="save-bar">
            <span class="muted small" data-dirty-note hidden>Ada perubahan yang belum disimpan.</span>
            <button class="btn btn-primary ms-auto" type="submit"><x-icon name="check"/> Simpan pengaturan</button>
        </div>
    </form>

    <form method="post" action="{{ route('settings.menus.groups.store') }}" class="panel mt-3 add-group">
        @csrf
        <label class="form-label" for="new-group">Tambah kelompok</label>
        <div class="d-flex gap-2 flex-wrap">
            <input class="form-control flex-grow-1" id="new-group" name="title" maxlength="120" placeholder="Misalnya Keuangan" required style="max-width:420px">
            <button class="btn btn-outline-primary" type="submit"><x-icon name="plus"/> Tambah kelompok</button>
        </div>
        <p class="form-text mb-0">Kelompok baru muncul di sidebar setelah berisi menu. Alamat halaman tidak bisa dibuat dari sini; menu hanya menunjuk halaman yang sudah ada.</p>
    </form>
    @foreach($groups as $g)
        @if(($children[$g->id] ?? collect())->isEmpty())
            <form id="delete-group-{{ $g->id }}" method="post" action="{{ route('settings.menus.groups.destroy', $g) }}" data-confirm="Hapus kelompok kosong {{ $g->title }}?" hidden>@csrf @method('DELETE')</form>
        @endif
    @endforeach

    <div class="modal fade" id="icon-modal" tabindex="-1" aria-labelledby="icon-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0"><h2 class="modal-title h5" id="icon-modal-title">Pilih ikon</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
                <div class="modal-body"><div class="icon-grid">
                    @foreach($icons as $icon)
                        <button type="button" class="icon-option" data-icon="{{ $icon }}" aria-label="{{ str_replace('-', ' ', $icon) }}" title="{{ str_replace('-', ' ', $icon) }}"><x-icon :name="$icon" :size="22"/></button>
                    @endforeach
                </div></div>
            </div>
        </div>
    </div>
</x-layouts.admin>
