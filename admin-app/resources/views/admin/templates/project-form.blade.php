@php use App\Support\Codes; @endphp
<x-layouts.admin :title="$template->exists ? 'Ubah template project' : 'Tambah template project'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('templates.projects.index') }}">Template Project</a></div>
        <h1>{{ $template->exists ? 'Ubah '.$template->name : 'Tambah template project' }}</h1></div></div>
    <form method="post" action="{{ $template->exists ? route('templates.projects.update', $template) : route('templates.projects.store') }}" class="panel" style="max-width:1120px">
        @csrf @if($template->exists) @method('PUT') @endif
        <div class="row g-2">
            <div class="col-md-8"><x-field name="name" label="Nama template" :value="$template->name" required/></div>
            <div class="col-md-4"><x-field name="project_type" label="Tipe" type="select" :options="Codes::PROJECT_TYPE" :value="$template->project_type" required/></div>
            <div class="col-12"><x-field name="description" label="Keterangan" type="textarea" :value="$template->description"/></div>
            <div class="col-12">
                <span class="form-label d-block" id="rows-label">Task dan checklist <span class="text-danger" aria-hidden="true">*</span></span><p class="small muted mb-2">Tipe sekali jadi: deadline dihitung dari hari mulai. Tipe berulang dan berkelanjutan: deadline ditulis sebagai tanggal (misalnya tgl 28), dan task bisa diulang setiap hari dengan jam kerja. Seret untuk mengubah urutan.</p><label class="visually-hidden" for="rows">Task dan checklist dalam teks</label>
                <textarea id="rows" name="rows" rows="10" class="form-control font-monospace @error('rows') is-invalid @enderror" required aria-describedby="rows-b" data-template-builder>{{ old('rows', $rows) }}</textarea>
                <div id="rows-b" class="form-text">Satu task per baris: <code>Judul task | hari ke- | subtask; subtask | pengulangan</code>. Contoh: <code>Desain UI/UX | 5 | Wireframe; Mockup</code> dan <code>Admin sosmed | | | daily 10:00-15:00</code> (daily = setiap hari, weekdays = Senin sampai Jumat). Hari ke- dihitung dari tanggal mulai project atau periode (hari ke-0 = tanggal 1 untuk periode bulanan).</div>
                @error('rows')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
        </div>
        <x-switch class="my-3" :checked="(bool) ($template->is_active ?? true)" help="Template nonaktif tidak muncul saat membuat project atau task."/>
        <p class="small muted">Mengubah template tidak mengubah project yang sudah dibuat dari template ini.</p>
        <button class="btn btn-primary" type="submit">Simpan template</button>
    </form>
</x-layouts.admin>
