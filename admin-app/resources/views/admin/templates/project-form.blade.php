@php use App\Support\Codes; @endphp
<x-layouts.admin :title="$template->exists ? 'Ubah template project' : 'Tambah template project'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('templates.projects.index') }}">Template Project</a></div>
        <h1>{{ $template->exists ? 'Ubah '.$template->name : 'Tambah template project' }}</h1></div></div>
    <form method="post" action="{{ $template->exists ? route('templates.projects.update', $template) : route('templates.projects.store') }}" class="panel" style="max-width:860px">
        @csrf @if($template->exists) @method('PUT') @endif
        <div class="row g-2">
            <div class="col-md-8"><x-field name="name" label="Nama template" :value="$template->name" required/></div>
            <div class="col-md-4"><x-field name="project_type" label="Tipe" type="select" :options="Codes::PROJECT_TYPE" :value="$template->project_type" required/></div>
            <div class="col-12"><x-field name="description" label="Keterangan" type="textarea" :value="$template->description"/></div>
            <div class="col-12">
                <label class="form-label" for="rows">Task dan subtask <span class="text-danger" aria-hidden="true">*</span></label>
                <textarea id="rows" name="rows" rows="10" class="form-control font-monospace @error('rows') is-invalid @enderror" required aria-describedby="rows-b">{{ old('rows', $rows) }}</textarea>
                <div id="rows-b" class="form-text">Satu task per baris: <code>Judul task | hari ke- | subtask; subtask</code>. Contoh: <code>Desain UI/UX | 5 | Wireframe; Mockup</code>. Hari ke- dihitung dari tanggal mulai project atau periode.</div>
                @error('rows')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="form-check my-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $template->is_active))><label class="form-check-label" for="is_active">Aktif</label></div>
        <p class="small muted">Mengubah template tidak mengubah project yang sudah dibuat dari template ini.</p>
        <button class="btn btn-primary" type="submit">Simpan template</button>
    </form>
</x-layouts.admin>
