{{-- Add task on one screen (U9, Technical Guide 6.3): project, title, PIC, assignees, and deadline together; no "Lanjut" step.
     Choosing a project refreshes PIC and assignee choices from that project's team (data-members) and the address the form posts to. --}}
@php
    $selectedId = old('project_id', $project?->id);
    $first = $selectedId ? collect($members[$selectedId] ?? []) : collect();
    $picDefault = old('pic_user_id', $first->contains('id', auth()->id()) ? auth()->id() : null);
@endphp
<form method="post" action="{{ $selectedId ? str_replace('__ID__', $selectedId, $storeUrl) : '' }}" class="panel task-create-form" style="max-width:680px" novalidate
      data-task-create data-url-template="{{ $storeUrl }}" data-members='@json($members)' data-me="{{ auth()->id() }}">
    @csrf
    <x-field name="project_id" label="Project" type="select" :options="$projects->pluck('name', 'id')" :value="$selectedId" empty="Pilih project" required/>
    <x-field name="title" label="Judul task" required/>
    <div class="row g-3">
        <div class="col-md-6">
            <x-field name="pic_user_id" label="PIC" type="select" :options="$first->mapWithKeys(fn ($p) => [$p['id'] => $p['name'].' ('.($p['open'] ?? 0).' task aktif)'])" :value="$picDefault" empty="Pilih project dulu" required hint="Penanggung jawab utama task. PIC memastikan task selesai dan ikut meninjau hasil; otomatis menjadi pelaksana."/>
        </div>
        <div class="col-md-6">
            <x-field name="due_at" label="Deadline" type="datetime-local" data-quick-dates/>
        </div>
    </div>
    <fieldset class="mb-3"><legend class="form-label">Pelaksana</legend>
        <div class="d-flex flex-wrap gap-2" data-assignee-list data-people-filter="label">
            @foreach($first as $person)
                <input type="checkbox" class="btn-check" name="assignees[]" value="{{ $person['id'] }}" id="ta{{ $person['id'] }}" autocomplete="off" @checked(in_array($person['id'], old('assignees', [])))>
                <label class="btn btn-sm btn-outline-primary" for="ta{{ $person['id'] }}">{{ $person['name'] }} <span class="load-count" title="Task aktif yang sedang dipegang">{{ $person['open'] ?? 0 }}</span></label>
            @endforeach
        </div>
        <div class="form-text" data-assignee-hint>{{ $selectedId ? 'Angka di samping nama = task aktif yang sedang dipegang. Hanya anggota project ini yang bisa dipilih.' : 'Pilih project untuk melihat anggotanya.' }}</div>
        @error('assignees')<div class="text-danger small">{{ $message }}</div>@enderror
    </fieldset>
    @if(count($taskTemplates))<x-field name="task_template_id" label="Pakai template task (opsional)" type="select" :options="$taskTemplates" empty="Tanpa template" help="Checklist dari template langsung dibuat."/>@endif
    <button class="btn btn-primary {{ $fullWidth ?? false ? 'w-100' : '' }}" type="submit"><x-icon name="plus"/> Tambah ke Antrian</button>
</form>
