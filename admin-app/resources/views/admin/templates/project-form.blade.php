@php use App\Support\Kode; @endphp
<x-layouts.admin :judul="$template->exists ? 'Ubah template project' : 'Tambah template project'">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('templates.projects.index') }}">Template Project</a></div>
        <h1>{{ $template->exists ? 'Ubah '.$template->name : 'Tambah template project' }}</h1></div></div>
    <form method="post" action="{{ $template->exists ? route('templates.projects.update', $template) : route('templates.projects.store') }}" class="kartu" style="max-width:860px">
        @csrf @if ($template->exists) @method('PUT') @endif
        <div class="row g-2">
            <div class="col-md-8"><x-isian nama="name" label="Nama template" :nilai="$template->name" wajib/></div>
            <div class="col-md-4"><x-isian nama="project_type" label="Tipe" jenis="select" :pilihan="Kode::PROJECT_TYPE" :nilai="$template->project_type" wajib/></div>
            <div class="col-12"><x-isian nama="description" label="Keterangan" jenis="textarea" :nilai="$template->description"/></div>
            <div class="col-12">
                <label class="form-label" for="baris">Task dan subtask <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea id="baris" name="baris" rows="10" class="form-control font-monospace @error('baris') is-invalid @enderror" required aria-describedby="baris-b">{{ old('baris', $baris) }}</textarea>
                <div id="baris-b" class="form-text">Satu task per baris: <code>Judul task | hari ke- | subtask; subtask</code>. Contoh: <code>Desain UI/UX | 5 | Wireframe; Mockup</code>. Hari ke- dihitung dari tanggal mulai project atau periode.</div>
                @error('baris')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="form-check my-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $template->is_active))><label class="form-check-label" for="is_active">Aktif</label></div>
        <p class="small teks-2">Mengubah template tidak mengubah project yang sudah dibuat dari template ini.</p>
        <button class="btn btn-primary" type="submit">Simpan template</button>
    </form>
</x-layouts.admin>
