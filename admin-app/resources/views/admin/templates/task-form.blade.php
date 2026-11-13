@php use App\Support\Codes; @endphp
<x-layouts.admin :title="$template->exists ? 'Ubah template task' : 'Tambah template task'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('templates.tasks.index') }}">Template Task</a></div>
        <h1>{{ $template->exists ? 'Ubah '.$template->name : 'Tambah template task' }}</h1></div></div>
    <form method="post" action="{{ $template->exists ? route('templates.tasks.update', $template) : route('templates.tasks.store') }}" class="panel" style="max-width:760px">
        @csrf @if($template->exists) @method('PUT') @endif
        <div class="row g-2">
            <div class="col-md-8"><x-field name="name" label="Nama template" :value="$template->name" required/></div>
            <div class="col-md-4"><x-field name="task_type" label="Jenis" type="select" :options="Codes::TASK_TYPE" :value="$template->task_type" required/></div>
            <div class="col-md-4"><x-field name="default_estimated_minutes" label="Estimasi (menit)" type="number" :value="$template->default_estimated_minutes"/></div>
            <div class="col-12"><x-field name="description" label="Keterangan" type="textarea" :value="$template->description"/></div>
            <div class="col-12"><label class="form-label" for="rows">Checklist subtask</label>
                <textarea id="rows" name="rows" rows="6" class="form-control" aria-describedby="rows-b">{{ old('rows', $rows) }}</textarea>
                <div id="rows-b" class="form-text">Satu checklist per baris, paling banyak 50.</div></div>
        </div>
        <div class="form-check my-3"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $template->is_active))><label class="form-check-label" for="is_active">Aktif</label></div>
        <button class="btn btn-primary" type="submit">Simpan template</button>
    </form>
</x-layouts.admin>
