@php
    $isSuper = $role->slug === 'super_admin';
    $locked = ! $canEdit || $isSuper;
    $cell = function ($permission, string $label) use ($granted, $locked, $role) {
        if (! $permission) {
            return '<span class="matrix-none" aria-hidden="true">-</span>';
        }
        $id = "r{$role->id}-p{$permission->id}";

        return '<label class="check-hit" for="'.$id.'"><input class="form-check-input" type="checkbox" name="permissions[]" value="'.$permission->id.'" id="'.$id.'"'
            .(in_array($permission->id, $granted, true) ? ' checked' : '').($locked ? ' disabled' : '').' aria-label="'.e($label).'"></label>';
    };
@endphp
<x-layouts.admin title="Role & Hak Akses">
    <div class="page-header"><div><div class="crumb">Pengaturan</div><h1>Role &amp; hak akses</h1>
        <p>Centang apa saja yang boleh dilakukan setiap role. Menu hanya menampilkan yang diizinkan, dan server tetap memeriksa izin di setiap permintaan.</p></div></div>

    <nav class="role-tabs" aria-label="Pilih role">
        @foreach($roles as $r)
            <a href="{{ route('settings.roles.index', ['role' => $r->slug]) }}" @if($r->id === $role->id) aria-current="page" @endif>
                <span class="fw-semibold">{{ $r->name }}</span>
                <span class="pill pill-plain pill-neutral numeric">{{ $r->slug === 'super_admin' ? 'Semua' : $r->permissions->count() }} izin</span></a>
        @endforeach
    </nav>

    <form method="post" action="{{ route('settings.roles.update', $role) }}" data-role-matrix>
        @csrf @method('PUT')
        <div class="panel">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <div class="flex-grow-1"><h2 class="panel-title mb-1">{{ $role->name }}</h2><div class="muted">{{ $role->description }}</div></div>
                <div class="role-search"><x-icon name="search"/><label class="visually-hidden" for="matrix-search">Cari menu</label>
                    <input class="form-control" id="matrix-search" type="search" placeholder="Cari menu..." data-matrix-search></div>
            </div>
            @if($isSuper)
                <div class="alert alert-info d-flex gap-2 align-items-center mb-3"><x-icon name="shield-check"/> Super Admin selalu memegang semua izin dan tidak bisa dikurangi.</div>
            @elseif(! $canEdit)
                <div class="alert alert-secondary mb-3">Kamu hanya bisa melihat. Mengubah hak akses butuh izin Kelola role &amp; hak akses.</div>
            @endif
            <div class="table-responsive">
                <table class="table matrix align-middle mb-0">
                    <thead><tr><th scope="col">Menu</th><th scope="col" class="text-center">Lihat</th><th scope="col" class="text-center">Tambah</th>
                        <th scope="col" class="text-center">Ubah</th><th scope="col" class="text-center">Hapus</th><th scope="col">Aksi khusus</th></tr></thead>
                    @foreach($matrix as $section => $rows)
                        <tbody data-matrix-section>
                            <tr class="matrix-section"><th scope="rowgroup" colspan="6">{{ $section }} <span class="muted fw-normal numeric" data-section-count></span></th></tr>
                            @foreach($rows as $row)
                                <tr data-matrix-row data-label="{{ \Illuminate\Support\Str::lower($row['label']) }}">
                                    <th scope="row" class="fw-semibold">{{ $row['label'] }}</th>
                                    <td class="text-center" data-col="view">{!! $cell($row['view'], 'Lihat '.$row['label']) !!}</td>
                                    @if($row['manage'])
                                        <td colspan="3" class="text-center manage-cell" data-col="manage">
                                            <label class="check-hit gap-2 px-2" for="r{{ $role->id }}-p{{ $row['manage']->id }}"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $row['manage']->id }}" id="r{{ $role->id }}-p{{ $row['manage']->id }}"
                                                @checked(in_array($row['manage']->id, $granted, true)) @disabled($locked)><span class="small">Tambah, ubah, dan hapus</span></label></td>
                                    @else
                                        <td class="text-center" data-col="create">{!! $cell($row['create'], 'Tambah '.$row['label']) !!}</td>
                                        <td class="text-center" data-col="update">{!! $cell($row['update'], 'Ubah '.$row['label']) !!}</td>
                                        <td class="text-center" data-col="delete">{!! $cell($row['delete'], 'Hapus '.$row['label']) !!}</td>
                                    @endif
                                    <td data-col="extra"><div class="extra-actions">
                                        @foreach($row['extra'] as [$permission, $name])
                                            <label class="extra-chip" for="r{{ $role->id }}-p{{ $permission->id }}"><input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="r{{ $role->id }}-p{{ $permission->id }}"
                                                @checked(in_array($permission->id, $granted, true)) @disabled($locked)>{{ $name }}</label>
                                        @endforeach
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>
            </div>
            <p class="muted small mt-3 mb-0" data-matrix-empty hidden>Tidak ada menu yang cocok dengan pencarian.</p>
        </div>
        @unless($locked)
            <div class="save-bar">
                <span class="muted small" data-dirty-note hidden>Ada perubahan yang belum disimpan.</span>
                <span class="muted small ms-auto">Mencentang Tambah, Ubah, Hapus, atau aksi khusus ikut mencentang Lihat.</span>
                <button class="btn btn-primary" type="submit"><x-icon name="check"/> Simpan hak akses {{ $role->name }}</button>
            </div>
        @endunless
    </form>
</x-layouts.admin>
