@php use App\Support\Waktu; @endphp
<x-layouts.tim judul="Tambah task">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('tasks.index') }}">Tasks</a></div><h1 class="mb-0">Tambah task</h1><p>Cukup judul, PIC, pelaksana, dan deadline. Sisanya bisa dilengkapi nanti.</p></div></div>
    @if ($projects->isEmpty())
        <div class="kartu"><p class="mb-0">Kamu belum bisa menambah task: belum menjadi anggota project aktif.</p></div>
    @elseif (! $project)
        <form method="get" class="kartu" style="max-width:640px">
            <x-isian nama="project" label="Project" jenis="select" :pilihan="$projects->pluck('name', 'id')" kosong="Pilih project" wajib/>
            <button class="btn btn-primary" type="submit">Lanjut</button>
        </form>
    @else
        <form method="post" action="{{ route('projects.tasks.store', $project) }}" class="kartu" style="max-width:640px">
            @csrf
            <p class="small teks-2 mb-3">Project: <strong>{{ $project->name }}</strong>@if($projects->count() > 1) · <a href="{{ route('tasks.create') }}">ganti</a>@endif</p>
            <x-isian nama="title" label="Judul task" wajib/>
            <x-isian nama="pic_user_id" label="PIC" jenis="select" :pilihan="$anggotaPilihan" :nilai="auth()->id()" wajib/>
            <fieldset class="mb-3"><legend class="form-label">Pelaksana</legend>
                <div class="d-flex flex-wrap gap-2">@foreach ($anggotaPilihan as $id => $nama)
                    <input type="checkbox" class="btn-check" name="assignees[]" value="{{ $id }}" id="a{{ $id }}" autocomplete="off" @checked(in_array($id, old('assignees', [])))>
                    <label class="btn btn-sm btn-outline-primary" for="a{{ $id }}">{{ $nama }}</label>@endforeach</div>
                <div class="form-text">PIC otomatis ikut sebagai pelaksana.</div>
                @error('assignees')<div class="text-danger small">{{ $message }}</div>@enderror</fieldset>
            <x-isian nama="due_at" label="Deadline" jenis="datetime-local"/>
            @if (count($templatesTask))<x-isian nama="task_template_id" label="Pakai template task (opsional)" jenis="select" :pilihan="$templatesTask" kosong="Tanpa template"/>@endif
            <button class="btn btn-primary w-100" type="submit"><x-ikon name="plus"/> Tambah ke Antrian</button>
        </form>
    @endif
</x-layouts.tim>
