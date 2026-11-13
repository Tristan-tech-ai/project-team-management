<x-layouts.admin title="Tambah Task">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('tasks.index') }}">Tasks</a></div><h1>Tambah Task</h1>
        <p>Cukup judul, PIC, pelaksana, dan deadline; status awal Antrian. Deskripsi, lampiran, dan subtask bisa dilengkapi nanti.</p></div></div>
    @if($projects->isEmpty())
        <div class="panel" style="max-width:640px"><p class="mb-0">Belum ada project aktif yang bisa kamu tambahi task.</p></div>
    @elseif(! $project)
        <form method="get" class="panel" style="max-width:640px">
            <x-field name="project" label="Project" type="select" :options="$projects->pluck('name', 'id')" empty="Pilih project" required/>
            <button class="btn btn-primary" type="submit">Lanjut</button>
        </form>
    @else
        <form method="post" action="{{ route('projects.tasks.store', $project) }}" class="panel" style="max-width:640px">
            @csrf
            <p class="small muted mb-3">Project: <strong>{{ $project->name }}</strong>@if($projects->count() > 1) · <a href="{{ route('tasks.create') }}">ganti</a>@endif</p>
            <x-field name="title" label="Judul task" required/>
            <x-field name="pic_user_id" label="PIC" type="select" :options="$memberOptions" :value="$memberOptions->has(auth()->id()) ? auth()->id() : null" empty="Pilih PIC" required/>
            <fieldset class="mb-3"><legend class="form-label">Pelaksana</legend>
                <div class="d-flex flex-wrap gap-2">@foreach($memberOptions as $id => $name)
                    <input type="checkbox" class="btn-check" name="assignees[]" value="{{ $id }}" id="a{{ $id }}" autocomplete="off" @checked(in_array($id, old('assignees', [])))>
                    <label class="btn btn-sm btn-outline-primary" for="a{{ $id }}">{{ $name }}</label>@endforeach</div>
                <div class="form-text">PIC otomatis ikut sebagai pelaksana. Hanya anggota project yang bisa dipilih.</div>
                @error('assignees')<div class="text-danger small">{{ $message }}</div>@enderror</fieldset>
            <x-field name="due_at" label="Deadline" type="datetime-local"/>
            @if(count($taskTemplates))<x-field name="task_template_id" label="Pakai template task (opsional)" type="select" :options="$taskTemplates" empty="Tanpa template"/>@endif
            <button class="btn btn-primary" type="submit"><x-icon name="plus"/> Tambah ke Antrian</button>
        </form>
    @endif
</x-layouts.admin>
