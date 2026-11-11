@php use App\Support\Kode; @endphp
<x-layouts.admin :judul="$template->exists ? 'Ubah template task' : 'Tambah template task'">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('templates.tasks.index') }}">Template Task</a></div>
        <h1>{{ $template->exists ? 'Ubah '.$template->name : 'Tambah template task' }}</h1></div></div>
    <form method="post" action="{{ $template->exists ? route('templates.tasks.update', $template) : route('templates.tasks.store') }}" class="kartu" style="max-width:760px">
        @csrf @if ($template->exists) @method('PUT') @endif
        <div class="row g-2">
            <div class="col-md-8"><x-isian nama="name" label="Nama template" :nilai="$template->name" wajib/></div>
            <div class="col-md-4"><x-isian nama="task_type" label="Jenis" jenis="select" :pilihan="Kode::TASK_TYPE" :nilai="$template->task_type" wajib/></div>
            <div class="col-md-4"><x-isian nama="default_estimated_minutes" label="Estimasi (menit)" jenis="number" :nilai="$template->default_estimated_minutes"/></div>
            <div class="col-12"><x-isian nama="description" label="Keterangan" jenis="textarea" :nilai="$template->description"/></div>
            <div class="col-12"><label class="form-label" for="baris">Checklist subtask</label>
                <textarea id="baris" name="baris" rows="6" class="form-control" aria-describedby="baris-b">{{ old('baris', $baris) }}</textarea>
                <div id="baris-b" class="form-text">Satu checklist per baris, paling banyak 50.</div></div>
        </div>
        <div class="form-check my-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $template->is_active))><label class="form-check-label" for="is_active">Aktif</label></div>
        <button class="btn btn-primary" type="submit">Simpan template</button>
    </form>
</x-layouts.admin>
