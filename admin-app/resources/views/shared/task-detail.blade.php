{{-- Task detail: checklist, PIC, members, attachments, report, time, review, history (Technical Guide 5.1, 5.2, 8.3). Used by Admin and Team. --}}
@php
    use App\Support\Codes;
    use App\Support\Dates;
    $late = $task->status !== 'done' && $task->due_at && $task->due_at->isPast();
    $subTotal = $task->subtasks->count();
    $subDone = $task->subtasks->where('status', 'done')->count();
    // A routine task (made from a template task that repeats daily) has one checklist item per day, each with its own deadline.
    $routine = $subTotal > 0 && $task->subtasks->every(fn ($s) => $s->due_at !== null);
    $todayKey = Dates::local(now())->toDateString();
    $routineToday = $routine ? $task->subtasks->first(fn ($s) => Dates::local($s->due_at)->toDateString() === $todayKey) : null;
    $routineMissed = $routine ? $task->subtasks->filter(fn ($s) => $s->status !== 'done' && Dates::local($s->due_at)->toDateString() < $todayKey)->values() : collect();
    $pending = $task->submissions->firstWhere('status', 'pending');
    $inPanel = $panel ?? false; // I3: in the side panel everything stacks in one column
@endphp
<div class="row g-3">
    <div class="{{ $inPanel ? 'col-12' : 'col-lg-8' }}">
        <div class="panel mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <x-badge :code="$late ? 'late' : $task->status" :text="($late ? 'Terlambat · ' : '').Codes::label(Codes::TASK_STATUS, $task->status)"/>
                <span class="pill pill-neutral">{{ Codes::label(Codes::TASK_TYPE, $task->task_type) }}</span>
                <span class="pill pill-neutral">Prioritas {{ mb_strtolower(Codes::label(Codes::PRIORITY, $task->priority)) }}</span>
                @if($task->period)<span class="pill pill-info">{{ $task->period->name }}</span>@endif
            </div>
            @php($lastResult = $task->submissions->sortByDesc('id')->first())
            @if($task->status === 'in_progress' && $lastResult?->status === 'revision')
                {{-- The revision note is what the person needs first after a result was returned. --}}
                <div class="revision-note mb-3" role="status"><x-icon name="rotate-ccw"/>
                    <div><div class="fw-semibold">Diminta revisi oleh {{ $lastResult->reviewer?->name ?? 'peninjau' }}</div><div>{{ $lastResult->review_note }}</div>
                        <div class="small muted mt-1">Perbaiki, lalu kirim hasil lagi. Hasil sebelumnya tetap tersimpan di riwayat.</div></div></div>
            @endif
            @if($task->description)<p class="mb-3" style="white-space:pre-line">{{ $task->description }}</p>@endif
            <dl class="row small mb-0">
                <dt class="col-sm-4 muted fw-normal">PIC</dt><dd class="col-sm-8">{{ $task->pic?->name }}</dd>
                <dt class="col-sm-4 muted fw-normal">Pelaksana</dt><dd class="col-sm-8">{{ $task->assignees->pluck('name')->implode(', ') ?: '-' }}</dd>
                <dt class="col-sm-4 muted fw-normal">Deadline</dt><dd class="col-sm-8 {{ $late ? 'text-danger fw-semibold' : '' }}">{{ Dates::dateTime($task->due_at) }}@if($task->due_at && $task->status !== 'done') <span class="muted fw-normal">· {{ Dates::relative($task->due_at) }}</span>@endif</dd>
                <dt class="col-sm-4 muted fw-normal">Target awal</dt><dd class="col-sm-8">{{ Dates::dateTime($task->baseline_due_at) }}@if($task->baseline_due_at && $task->due_at && ! $task->baseline_due_at->equalTo($task->due_at)) <span class="pill pill-risk">bergeser {{ (int) round($task->baseline_due_at->diffInDays($task->due_at, false)) }} hari</span>@endif</dd>
                <dt class="col-sm-4 muted fw-normal">Selesai aktual</dt><dd class="col-sm-8">{{ $task->completed_at ? Dates::dateTime($task->completed_at) : '-' }}</dd>
                <dt class="col-sm-4 muted fw-normal">Waktu kerja tercatat</dt><dd class="col-sm-8 numeric">{{ $workMinutes }} menit @if($task->estimated_minutes)<span class="muted">dari estimasi {{ $task->estimated_minutes }} menit</span>@endif</dd>
                @if($task->scheduled_publish_at || $task->platform)
                    <dt class="col-sm-4 muted fw-normal">Publikasi</dt><dd class="col-sm-8">{{ $task->platform ?? '-' }} · {{ Dates::dateTime($task->scheduled_publish_at) }} · {{ Codes::label(Codes::PUBLICATION_STATUS, $task->publication_status) }}@if($task->publication_url) · <a href="{{ $task->publication_url }}" rel="noopener nofollow" target="_blank">tautan</a>@endif</dd>
                @endif
            </dl>
            {{-- One primary action per status (Technical Guide 6.3). --}}
            <div class="d-flex flex-wrap gap-2 mt-3">
                @if($task->status === 'backlog' && $can['status'])
                    <form method="post" action="{{ route('tasks.status', $task) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="in_progress"><button class="btn btn-primary" type="submit"><x-icon name="send"/> Mulai</button></form>
                @elseif($task->status === 'in_progress' && $can['send'])
                    <a class="btn btn-primary" href="#submit-result"><x-icon name="send"/> Kirim untuk Review</a>
                @elseif($task->status === 'review' && $can['review'] && $pending)
                    <a class="btn btn-primary" href="#review"><x-icon name="clipboard-check"/> Terima atau revisi</a>
                @elseif($task->status === 'review')
                    <span class="muted small">Menunggu PIC atau manager meninjau hasil.</span>
                @elseif($task->status === 'done' && $can['review'])
                    <form method="post" action="{{ route('tasks.status', $task) }}" data-confirm="Buka kembali task ini? Tanggal selesai akan dikosongkan.">@csrf @method('PATCH')<input type="hidden" name="status" value="in_progress"><button class="btn btn-outline-secondary" type="submit"><x-icon name="rotate-ccw"/> Buka kembali</button></form>
                @endif
            </div>
        </div>

        <div class="panel mb-3" id="checklist">
            <div class="d-flex align-items-center justify-content-between mb-2"><h2 class="panel-title mb-0">{{ $routine ? 'Checklist harian' : 'Checklist' }}</h2><span class="small muted numeric" data-checklist-count>{{ $subDone }} / {{ $subTotal }}</span></div>
            @if($subTotal)<div class="meter mb-3"><span data-checklist-progress style="width:{{ $subTotal ? round($subDone * 100 / $subTotal) : 0 }}%"></span></div>@endif
            @php($canTick = $can['status'] || $can['update'])
            @if($routine)
                {{-- A routine (one dated item per day): today first, then days that were missed, the rest folded away. --}}
                <p class="small muted mb-2">Task rutin: centang setiap hari setelah dikerjakan.</p>
                @if($routineToday)
                    <div class="routine-today mb-2"><span class="routine-label">Hari ini</span>
                        <ul class="list-unstyled m-0 checklist">@include('shared.checklist-item', ['s' => $routineToday, 'canTick' => $canTick])</ul></div>
                @else
                    <p class="small mb-2">Tidak ada jadwal hari ini.</p>
                @endif
                @if($routineMissed->isNotEmpty())
                    <div class="small fw-semibold text-danger mt-2">Belum dicentang ({{ $routineMissed->count() }} hari)</div>
                    <ul class="list-unstyled mb-2 checklist">@foreach($routineMissed as $s)@include('shared.checklist-item', ['s' => $s, 'canTick' => $canTick])@endforeach</ul>
                @endif
                @php($others = $task->subtasks->reject(fn ($s) => $s->is($routineToday) || $routineMissed->contains($s)))
                @if($others->isNotEmpty())
                    <details class="routine-all"><summary class="small">Hari lain ({{ $others->count() }})</summary>
                        <ul class="list-unstyled mt-2 mb-0 checklist">@foreach($others as $s)@include('shared.checklist-item', ['s' => $s, 'canTick' => $canTick])@endforeach</ul></details>
                @endif
            @else
                <ul class="list-unstyled mb-2 checklist">
                    @forelse($task->subtasks as $s)
                        @include('shared.checklist-item', ['s' => $s, 'canTick' => $canTick])
                    @empty
                        <li class="muted small">Belum ada checklist.</li>
                    @endforelse
                </ul>
            @endif
            @if($can['update'])
                <form method="post" action="{{ route('tasks.subtasks.store', $task) }}" class="d-flex gap-2">@csrf
                    <label class="visually-hidden" for="new-subtask">Checklist baru</label>
                    <input id="new-subtask" name="title" class="form-control" placeholder="Tambah checklist" required maxlength="255">
                    <button class="btn btn-outline-primary" type="submit"><x-icon name="plus"/> Tambah</button></form>
            @endif
        </div>

        @if($can['send'] && in_array($task->status, ['backlog', 'in_progress'], true))
            <form method="post" action="{{ route('tasks.submissions.store', $task) }}" enctype="multipart/form-data" class="panel mb-3" id="submit-result">
                @csrf
                <h2 class="panel-title">Kirim hasil untuk review</h2>
                <x-field name="external_url" label="Tautan hasil" type="url" help="Misalnya tautan Google Drive, Figma, atau halaman yang sudah jadi."/>
                <div class="mb-3"><span class="form-label d-block" id="file-label">Atau unggah berkas</span>
                    <label class="file-pick @error('file') is-invalid @enderror" for="file"><span class="btn btn-outline-secondary btn-sm"><x-icon name="paperclip" :size="16"/> Pilih berkas</span>
                        <span class="file-name muted small" data-file-name>Belum ada berkas dipilih</span>
                        <input class="visually-hidden" type="file" id="file" name="file" aria-labelledby="file-label" data-file-input></label>
                    <div class="form-text">PDF, gambar, dokumen Office, ZIP, MP4, paling besar 10 MB.</div>
                    @error('file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <x-field name="submission_note" label="Catatan singkat" type="textarea"/>
                <button class="btn btn-primary" type="submit"><x-icon name="send"/> Kirim untuk Review</button>
            </form>
        @endif

        @if($pending && $can['review'])
            <div class="panel mb-3" id="review">
                <h2 class="panel-title">Tinjau hasil dari {{ $pending->submitter?->name }}</h2>
                @include('shared.submission-content', ['s' => $pending])
                <div class="row g-2 mt-2">
                    {{-- Grid breakpoints follow the screen, not the panel width: in the panel both parts take the full width. --}}
                    <div class="{{ $inPanel ? 'col-12' : 'col-md-4' }}"><form method="post" action="{{ route('submissions.review', $pending) }}">@csrf<input type="hidden" name="decision" value="approved">
                        <button class="btn btn-success w-100" type="submit"><x-icon name="circle-check"/> Terima, task selesai</button></form></div>
                    <div class="{{ $inPanel ? 'col-12' : 'col-md-8' }}"><form method="post" action="{{ route('submissions.review', $pending) }}">@csrf<input type="hidden" name="decision" value="revision">
                        <label class="form-label" for="rv-{{ $pending->id }}">Alasan revisi</label>
                        <textarea id="rv-{{ $pending->id }}" name="review_note" class="form-control mb-2 @error('review_note') is-invalid @enderror" rows="2" required minlength="5" placeholder="Apa yang perlu diperbaiki?">{{ old('review_note') }}</textarea>
                        @error('review_note')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <button class="btn btn-outline-danger" type="submit"><x-icon name="rotate-ccw"/> Kembalikan untuk revisi</button></form></div>
                </div>
            </div>
        @endif

        <div class="panel mb-3">
            <h2 class="panel-title">Riwayat hasil</h2>
            @forelse($task->submissions as $s)
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center"><x-badge :code="$s->status" :text="Codes::label(Codes::SUBMISSION_STATUS, $s->status)"/>
                        <span class="small muted">{{ $s->submitter?->name }} · {{ Dates::dateTime($s->submitted_at) }}</span></div>
                    @include('shared.submission-content', ['s' => $s])
                    @if($s->review_note)<p class="small mb-0 mt-1"><strong>{{ $s->status === 'revision' ? 'Catatan revisi' : 'Catatan review' }} ({{ $s->reviewer?->name }}):</strong> {{ $s->review_note }}</p>@endif
                </div>
            @empty
                <p class="muted small mb-0">Belum ada hasil yang dikirim.</p>
            @endforelse
        </div>
    </div>

    <div class="{{ $inPanel ? 'col-12' : 'col-lg-4' }}">
        @if($can['status'])
            <form method="post" action="{{ route('tasks.time.store', $task) }}" class="panel mb-3">@csrf
                <h2 class="panel-title">Catat waktu kerja</h2>
                <input type="hidden" name="work_date" value="{{ now(Dates::zone())->toDateString() }}">
                <div class="d-flex flex-wrap gap-2">@foreach([15, 30, 60, 120, 240] as $m)
                    <button class="btn btn-sm btn-outline-primary" type="submit" name="minutes_spent" value="{{ $m }}">{{ $m < 60 ? $m.' menit' : ($m / 60).' jam' }}</button>@endforeach</div>
                <p class="small muted mt-2 mb-0">Dicatat untuk hari ini.</p>
            </form>
        @endif

        @if($can['assign'])
            <form method="post" action="{{ route('tasks.assignees', $task) }}" class="panel mb-3" data-pic-handover data-status="{{ $task->status }}" data-pic="{{ $task->pic_user_id }}">@csrf @method('PUT')
                <h2 class="panel-title">PIC dan pelaksana</h2>
                <x-field name="pic_user_id" label="PIC" type="select" :options="$memberOptions" :value="$task->pic_user_id" required/>
                @php($load = \App\Support\ProjectPeople::openTasks(collect($memberOptions)->keys()->map(fn ($k) => (int) $k)->all()))
                <fieldset class="mb-2"><legend class="form-label">Pelaksana</legend>
                    {{-- I5: the number beside a name is how many open tasks that person holds; long lists get a name search. --}}
                    <div data-people-filter=".form-check">
                    @foreach($memberOptions as $id => $name)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="assignees[]" value="{{ $id }}" id="as-{{ $id }}" @checked($task->assignees->contains('id', $id))>
                            <label class="form-check-label" for="as-{{ $id }}">{{ $name }} <span class="load-count" title="Task aktif yang sedang dipegang">{{ $load[$id] ?? 0 }}</span></label></div>
                    @endforeach
                    </div>
                    @error('assignees')<div class="text-danger small">{{ $message }}</div>@enderror
                </fieldset>
                <button class="btn btn-outline-primary btn-sm" type="submit">Simpan PIC dan pelaksana</button>
            </form>
        @endif

        @if($task->status !== 'done' && ($can['update'] || $can['status']))
            <details class="panel mb-3 fold-panel" @if($errors->has('proposed_due_at') || $errors->has('reason')) open @endif>
            <summary class="panel-title mb-0">Butuh geser deadline?</summary>
            <form method="post" action="{{ route('schedule-changes.store') }}" class="mt-3">@csrf
                <input type="hidden" name="project_id" value="{{ $task->project_id }}"><input type="hidden" name="task_id" value="{{ $task->id }}">
                @if($task->due_at)
                    {{-- A shift is counted from the current deadline (+3/+7/+14 days, same time), not from today. --}}
                    <x-field name="proposed_due_at" label="Deadline usulan" type="datetime-local" required hint="Deadline baru yang kamu ajukan. Berlaku setelah disetujui PIC atau manager; target awal tetap tercatat."/>
                    <div class="quick-dates mt-n2 mb-3" role="group" aria-label="Geser dari deadline sekarang ({{ Dates::dateTime($task->due_at) }})" data-base-project="{{ Dates::forInput($task->due_at) }}">
                        <span class="small muted me-1">Dari deadline sekarang:</span>
                        @foreach([3, 7, 14] as $days)<button type="button" class="quick-date" data-fill-days="{{ $days }}" data-target="#f-proposed_due_at">+{{ $days }} hari</button>@endforeach
                    </div>
                @else
                    <x-field data-quick-dates name="proposed_due_at" label="Deadline usulan" type="datetime-local" required hint="Deadline baru yang kamu ajukan. Berlaku setelah disetujui PIC atau manager; target awal tetap tercatat."/>
                @endif
                <x-field name="reason" label="Alasan" type="textarea" required help="Disetujui PIC berizin atau manager. Target awal tetap tercatat."/>
                <button class="btn btn-outline-primary btn-sm" type="submit">Ajukan</button>
            </form>
            </details>
        @endif
        @if($schedule->isNotEmpty())
            @php($scheduleService = app(\App\Services\ScheduleChangeService::class))
            <div class="panel mb-3" id="schedule"><h2 class="panel-title">Perubahan jadwal</h2>
                @foreach($schedule as $j)
                    <div class="small border-bottom pb-2 mb-2"><x-badge :code="$j->status" :text="Codes::label(Codes::SCHEDULE_STATUS, $j->status)"/>
                        <div>{{ Dates::date($j->previous_due_at) }} menjadi {{ Dates::date($j->proposed_due_at) }}</div>
                        <div class="muted">{{ $j->reason }} · {{ $j->requester?->name }}</div>
                        {{-- The "Putuskan geser deadline" item in Perlu kamu kerjakan opens this task, so the decision is made here too. --}}
                        @if($j->status === 'pending' && (int) $j->requested_by !== (int) auth()->id() && $scheduleService->canDecide(auth()->user(), $j))
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}">@csrf<input type="hidden" name="decision" value="approved">
                                    <button class="btn btn-sm btn-success" type="submit"><x-icon name="circle-check" :size="16"/> Setujui</button></form>
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}" class="d-flex gap-2 flex-grow-1">@csrf<input type="hidden" name="decision" value="rejected">
                                    <label class="visually-hidden" for="sc-{{ $j->id }}">Alasan penolakan</label>
                                    <input id="sc-{{ $j->id }}" name="decision_note" class="form-control form-control-sm" placeholder="Alasan penolakan" required minlength="5">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button></form>
                            </div>
                        @endif
                    </div>
                @endforeach</div>
        @endif

        @if($can['update'])
            <details class="panel mb-3 fold-panel"><summary class="panel-title mb-0">Ubah isi task</summary>
                <form method="post" action="{{ route('tasks.update', $task) }}" class="mt-3">@csrf @method('PUT')
                    <x-field name="title" label="Judul" :value="$task->title" required/>
                    <x-field name="description" label="Deskripsi" type="textarea" :value="$task->description"/>
                    <x-field name="priority" label="Prioritas" type="select" :options="Codes::PRIORITY" :value="$task->priority" required/>
                    <x-field name="task_type" label="Jenis" type="select" :options="Codes::TASK_TYPE" :value="$task->task_type" required/>
                    <x-field name="estimated_minutes" label="Estimasi (menit)" type="number" :value="$task->estimated_minutes"/>
                    @unless($task->baseline_due_at)<x-field name="due_at" label="Deadline" type="datetime-local" :value="Dates::forInput($task->due_at)" help="Setelah diisi, deadline menjadi target awal dan hanya bisa digeser lewat pengajuan."/>@endunless
                    <x-field name="platform" label="Platform publikasi" :value="$task->platform" help="Untuk task konten, misalnya Instagram."/>
                    <x-field name="scheduled_publish_at" label="Jadwal publikasi" type="datetime-local" :value="Dates::forInput($task->scheduled_publish_at)"/>
                    <x-field name="published_at" label="Tanggal terbit" type="datetime-local" :value="Dates::forInput($task->published_at)"/>
                    <x-field name="publication_status" label="Status publikasi" type="select" :options="Codes::PUBLICATION_STATUS" :value="$task->publication_status" empty="Bukan konten"/>
                    <x-field name="publication_url" label="Tautan publikasi" type="url" :value="$task->publication_url"/>
                    <button class="btn btn-primary btn-sm" type="submit">Simpan</button>
                </form>
                @if($can['delete'])
                    <form method="post" action="{{ route('tasks.destroy', $task) }}" class="mt-3" data-confirm="Hapus task {{ $task->title }}? Task disimpan sebagai terhapus dan bisa dipulihkan.">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit"><x-icon name="trash-2"/> Hapus task</button></form>
                @endif
            </details>
        @endif

        <div class="panel">
            <h2 class="panel-title">Riwayat</h2>
            <ol class="list-unstyled small mb-0">
                @foreach($task->activities as $a)
                    <li class="mb-2"><div>{{ $a->description }}</div><div class="muted">{{ $a->user?->name }} · {{ Dates::dateTime($a->created_at) }}</div></li>
                @endforeach
            </ol>
        </div>
    </div>
</div>
