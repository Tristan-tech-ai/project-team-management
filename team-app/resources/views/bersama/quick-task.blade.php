{{-- Quick Add Task (Petunjuk Teknis 6.3): judul, PIC, pelaksana, deadline. Project otomatis bila dibuka dari project. --}}
@php($idModal = 'quick-task-'.$project->id)
<div class="modal fade" id="{{ $idModal }}" tabindex="-1" aria-labelledby="{{ $idModal }}-j" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <form method="post" action="{{ route('projects.tasks.store', $project) }}" class="modal-content" style="border-radius:16px">
            @csrf
            <div class="modal-header"><h2 class="modal-title h5" id="{{ $idModal }}-j">Tambah task di {{ $project->name }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
            <div class="modal-body">
                <x-isian nama="title" label="Judul task" wajib/>
                <x-isian nama="pic_user_id" label="PIC" jenis="select" :pilihan="$anggotaPilihan" :nilai="auth()->id()" wajib/>
                <fieldset class="mb-3"><legend class="form-label">Pelaksana</legend>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($anggotaPilihan as $id => $nama)
                            <input type="checkbox" class="btn-check" name="assignees[]" value="{{ $id }}" id="{{ $idModal }}-a{{ $id }}" autocomplete="off">
                            <label class="btn btn-sm btn-outline-primary" for="{{ $idModal }}-a{{ $id }}">{{ $nama }}</label>
                        @endforeach
                    </div>
                    <div class="form-text">PIC otomatis ikut sebagai pelaksana. Boleh lebih dari satu orang, lintas divisi.</div>
                </fieldset>
                <x-isian nama="due_at" label="Deadline" jenis="datetime-local"/>
                @if (! empty($templatesTask) && count($templatesTask))
                    <x-isian nama="task_template_id" label="Pakai template task (opsional)" jenis="select" :pilihan="$templatesTask" kosong="Tanpa template" bantuan="Checklist subtask dari template langsung dibuat."/>
                @endif
                @if (! empty($periodeDipilih))<input type="hidden" name="project_period_id" value="{{ $periodeDipilih }}">@endif
                <p class="small teks-2 mb-0">Status awal Antrian. Deskripsi, lampiran, dan checklist bisa ditambah nanti.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Tambah task</button></div>
        </form>
    </div>
</div>
