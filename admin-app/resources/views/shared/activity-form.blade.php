{{-- Add Activity: meeting, visit, revision, blocker (quick form). Actual participants = project members who really attended. --}}
@php use App\Support\Codes; @endphp
<form method="post" action="{{ route('projects.activities.store', $project) }}" class="panel">
    @csrf
    @if(! empty($after))<input type="hidden" name="after" value="{{ $after }}">@endif
    <h2 class="panel-title">Catat aktivitas</h2>
    <div class="row g-2">
        <div class="col-sm-6"><x-field name="activity_type" label="Jenis" type="select" :options="Codes::ACTIVITY_TYPE" value="meeting" required/></div>
        <div class="col-sm-6"><x-field name="status" label="Status" type="select" :options="Codes::ACTIVITY_STATUS" value="done" required/></div>
        <div class="col-12"><x-field name="title" label="Judul" required/></div>
        <div class="col-sm-6"><x-field name="occurred_at" label="Tanggal kejadian" type="datetime-local" :value="\App\Support\Dates::forInput(now())"/></div>
        <div class="col-sm-6"><x-field name="scheduled_at" label="Tanggal terjadwal" type="datetime-local"/></div>
        <div class="col-sm-6">
            <label class="form-label" for="dur-{{ $project->id }}">Durasi</label>
            <div class="d-flex gap-1 flex-wrap mb-1">@foreach([15, 30, 60, 120] as $m)<button type="button" class="btn btn-sm btn-outline-secondary" data-fill-duration="{{ $m }}" data-target="#dur-{{ $project->id }}">{{ $m < 60 ? $m.' mnt' : ($m / 60).' jam' }}</button>@endforeach</div>
            <input id="dur-{{ $project->id }}" class="form-control numeric" type="number" min="0" name="duration_minutes" value="{{ old('duration_minutes') }}" placeholder="menit">
        </div>
        <div class="col-sm-6"><x-field name="impact_type" label="Dampak pada jadwal" type="select" :options="Codes::IMPACT_TYPE" value="none" required/></div>
        @if(! empty($taskOptions) && count($taskOptions))
            <div class="col-12"><x-field name="task_id" label="Task terkait (opsional)" type="select" :options="$taskOptions" empty="Tidak ada"/></div>
        @endif
        <div class="col-12"><fieldset><legend class="form-label">Peserta yang hadir</legend>
            <div class="d-flex flex-wrap gap-2">@foreach($memberOptions as $id => $name)
                <input type="checkbox" class="btn-check" name="participants[]" value="{{ $id }}" id="pa-{{ $project->id }}-{{ $id }}" autocomplete="off" @checked(in_array($id, old('participants', [])))>
                <label class="btn btn-sm btn-outline-primary" for="pa-{{ $project->id }}-{{ $id }}">{{ $name }}</label>@endforeach</div>
            @error('participants.*')<div class="text-danger small">{{ $message }}</div>@enderror</fieldset></div>
        <div class="col-12"><x-field name="description" label="Catatan" type="textarea"/></div>
        <div class="col-12"><x-field name="impact_notes" label="Penjelasan dampak" type="textarea" help="Mencatat rapat atau kunjungan tidak otomatis membebaskan keterlambatan."/></div>
        <div class="col-12">
            <details class="mb-2"><summary class="fw-semibold">Butuh geser deadline?</summary>
                <div class="row g-2 mt-1">
                    @php
                        // Quick shift base: the selected task deadline, or the project deadline, or today when there is none.
                        $taskDeadline = ! empty($taskOptions) && count($taskOptions)
                            ? \App\Models\Task::query()->whereIn('id', collect($taskOptions)->keys())->whereNotNull('due_at')->get(['id', 'due_at'])
                                ->mapWithKeys(fn ($t) => [$t->id => \App\Support\Dates::forInput($t->due_at)])
                            : collect();
                    @endphp
                    <div class="col-sm-6">
                        <p class="form-text mt-0 mb-1">Deadline project sekarang: {{ $project->due_at ? \App\Support\Dates::dateTime($project->due_at) : 'belum ada' }}</p>
                        <div class="d-flex gap-1 flex-wrap mb-1" role="group" aria-label="Geser cepat dari deadline sekarang" data-base-project="{{ \App\Support\Dates::forInput($project->due_at) }}" data-base-task="{{ json_encode($taskDeadline) }}" data-select-task="#f-task_id">@foreach([7, 14, 30] as $h)<button type="button" class="btn btn-sm btn-outline-secondary" data-fill-days="{{ $h }}" data-target="#f-propose_deadline">+{{ $h }} hari</button>@endforeach</div>
                        <x-field name="propose_deadline" label="Deadline usulan" type="datetime-local"/></div>
                    <div class="col-sm-6"><x-field name="deadline_reason" label="Alasan" help="Diajukan ke PIC atau manager. Target awal tidak berubah."/></div>
                </div>
            </details>
        </div>
    </div>
    <button class="btn btn-primary" type="submit">Simpan aktivitas</button>
</form>
