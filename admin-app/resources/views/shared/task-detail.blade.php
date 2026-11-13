{{-- Task detail: checklist, PIC, members, attachments, report, time, review, history (Technical Guide 5.1, 5.2, 8.3). Used by Admin and Team. --}}
@php
    use App\Support\Codes;
    use App\Support\Dates;
    $late = $task->status !== 'done' && $task->due_at && $task->due_at->isPast();
    $subTotal = $task->subtasks->count();
    $subDone = $task->subtasks->where('status', 'done')->count();
    $pending = $task->submissions->firstWhere('status', 'pending');
@endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="panel mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <x-badge :code="$late ? 'late' : $task->status" :text="($late ? 'Terlambat · ' : '').Codes::label(Codes::TASK_STATUS, $task->status)"/>
                <span class="pill pill-neutral">{{ Codes::label(Codes::TASK_TYPE, $task->task_type) }}</span>
                <span class="pill pill-neutral">Prioritas {{ mb_strtolower(Codes::label(Codes::PRIORITY, $task->priority)) }}</span>
                @if($task->period)<span class="pill pill-info">{{ $task->period->name }}</span>@endif
            </div>
            @if($task->description)<p class="mb-3" style="white-space:pre-line">{{ $task->description }}</p>@endif
            <dl class="row small mb-0">
                <dt class="col-sm-4 muted fw-normal">PIC</dt><dd class="col-sm-8">{{ $task->pic?->name }}</dd>
                <dt class="col-sm-4 muted fw-normal">Pelaksana</dt><dd class="col-sm-8">{{ $task->assignees->pluck('name')->implode(', ') ?: '-' }}</dd>
                <dt class="col-sm-4 muted fw-normal">Deadline</dt><dd class="col-sm-8 {{ $late ? 'text-danger fw-semibold' : '' }}">{{ Dates::dateTime($task->due_at) }}</dd>
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
            <div class="d-flex align-items-center justify-content-between mb-2"><h2 class="panel-title mb-0">Checklist</h2><span class="small muted numeric" data-checklist-count>{{ $subDone }} / {{ $subTotal }}</span></div>
            @if($subTotal)<div class="meter mb-3"><span data-checklist-progress style="width:{{ $subTotal ? round($subDone * 100 / $subTotal) : 0 }}%"></span></div>@endif
            <ul class="list-unstyled mb-2 checklist">
                @forelse($task->subtasks as $s)
                    <li class="d-flex align-items-center gap-2 py-1">
                        <form method="post" action="{{ route('subtasks.toggle', $s) }}" class="d-flex align-items-center gap-2 m-0">@csrf @method('PATCH')
                            <input type="hidden" name="done" value="{{ $s->status === 'done' ? 0 : 1 }}">
                            <input class="form-check-input mt-0" type="checkbox" id="s-{{ $s->id }}" data-check-subtask="{{ route('subtasks.toggle', $s) }}" @checked($s->status === 'done') @disabled(! ($can['status'] || $can['update']))>
                            <label for="s-{{ $s->id }}" class="{{ $s->status === 'done' ? 'text-decoration-line-through muted' : '' }}">{{ $s->title }}</label>
                            <noscript><button class="btn btn-sm btn-outline-secondary" type="submit">{{ $s->status === 'done' ? 'Buka' : 'Selesai' }}</button></noscript>
                        </form>
                    </li>
                @empty
                    <li class="muted small">Belum ada checklist.</li>
                @endforelse
            </ul>
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
                <div class="mb-3"><label class="form-label" for="file">Atau unggah berkas</label>
                    <input class="form-control @error('file') is-invalid @enderror" type="file" id="file" name="file">
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
                    <div class="col-md-4"><form method="post" action="{{ route('submissions.review', $pending) }}">@csrf<input type="hidden" name="decision" value="approved">
                        <button class="btn btn-success w-100" type="submit"><x-icon name="circle-check"/> Terima, task selesai</button></form></div>
                    <div class="col-md-8"><form method="post" action="{{ route('submissions.review', $pending) }}">@csrf<input type="hidden" name="decision" value="revision">
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

    <div class="col-lg-4">
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
            <form method="post" action="{{ route('tasks.assignees', $task) }}" class="panel mb-3">@csrf @method('PUT')
                <h2 class="panel-title">PIC dan pelaksana</h2>
                <x-field name="pic_user_id" label="PIC" type="select" :options="$memberOptions" :value="$task->pic_user_id" required/>
                <fieldset class="mb-2"><legend class="form-label">Pelaksana</legend>
                    @foreach($memberOptions as $id => $name)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="assignees[]" value="{{ $id }}" id="as-{{ $id }}" @checked($task->assignees->contains('id', $id))>
                            <label class="form-check-label" for="as-{{ $id }}">{{ $name }}</label></div>
                    @endforeach
                    @error('assignees')<div class="text-danger small">{{ $message }}</div>@enderror
                </fieldset>
                <button class="btn btn-outline-primary btn-sm" type="submit">Simpan pelaksana</button>
            </form>
        @endif

        @if($task->status !== 'done' && ($can['update'] || $can['status']))
            <form method="post" action="{{ route('schedule-changes.store') }}" class="panel mb-3">@csrf
                <h2 class="panel-title">Ajukan perubahan deadline</h2>
                <input type="hidden" name="project_id" value="{{ $task->project_id }}"><input type="hidden" name="task_id" value="{{ $task->id }}">
                <x-field name="proposed_due_at" label="Deadline usulan" type="datetime-local" required/>
                <x-field name="reason" label="Alasan" type="textarea" required help="Disetujui PIC berizin atau manager. Target awal tetap tercatat."/>
                <button class="btn btn-outline-primary btn-sm" type="submit">Ajukan</button>
            </form>
        @endif
        @if($schedule->isNotEmpty())
            <div class="panel mb-3"><h2 class="panel-title">Perubahan jadwal</h2>
                @foreach($schedule as $j)
                    <div class="small border-bottom pb-2 mb-2"><x-badge :code="$j->status" :text="Codes::label(Codes::SCHEDULE_STATUS, $j->status)"/>
                        <div>{{ Dates::date($j->previous_due_at) }} menjadi {{ Dates::date($j->proposed_due_at) }}</div>
                        <div class="muted">{{ $j->reason }} · {{ $j->requester?->name }}</div></div>
                @endforeach</div>
        @endif

        @if($can['update'])
            <details class="panel mb-3"><summary class="fw-semibold">Ubah isi task</summary>
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
