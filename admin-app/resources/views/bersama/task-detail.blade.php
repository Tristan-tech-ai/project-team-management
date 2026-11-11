{{-- Detail task: checklist, PIC, anggota, lampiran, laporan, waktu, review, riwayat (Petunjuk Teknis 5.1, 5.2, 8.3). Dipakai Admin dan Tim. --}}
@php
    use App\Support\Kode;
    use App\Support\Waktu;
    $telat = $task->status !== 'done' && $task->due_at && $task->due_at->isPast();
    $subTotal = $task->subtasks->count();
    $subSelesai = $task->subtasks->where('status', 'done')->count();
    $pending = $task->submissions->firstWhere('status', 'pending');
@endphp
<div class="row g-3">
    <div class="col-lg-8">
        <div class="kartu mb-3">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <x-lencana :kode="$telat ? 'telat' : $task->status" :teks="($telat ? 'Terlambat · ' : '').Kode::label(Kode::TASK_STATUS, $task->status)"/>
                <span class="lencana lencana-netral">{{ Kode::label(Kode::TASK_TYPE, $task->task_type) }}</span>
                <span class="lencana lencana-netral">Prioritas {{ mb_strtolower(Kode::label(Kode::PRIORITY, $task->priority)) }}</span>
                @if ($task->period)<span class="lencana lencana-info">{{ $task->period->name }}</span>@endif
            </div>
            @if ($task->description)<p class="mb-3" style="white-space:pre-line">{{ $task->description }}</p>@endif
            <dl class="row small mb-0">
                <dt class="col-sm-4 teks-2 fw-normal">PIC</dt><dd class="col-sm-8">{{ $task->pic?->name }}</dd>
                <dt class="col-sm-4 teks-2 fw-normal">Pelaksana</dt><dd class="col-sm-8">{{ $task->assignees->pluck('name')->implode(', ') ?: '-' }}</dd>
                <dt class="col-sm-4 teks-2 fw-normal">Deadline</dt><dd class="col-sm-8 {{ $telat ? 'text-danger fw-semibold' : '' }}">{{ Waktu::tanggalJam($task->due_at) }}</dd>
                <dt class="col-sm-4 teks-2 fw-normal">Target awal</dt><dd class="col-sm-8">{{ Waktu::tanggalJam($task->baseline_due_at) }}@if($task->baseline_due_at && $task->due_at && ! $task->baseline_due_at->equalTo($task->due_at)) <span class="lencana lencana-risk">bergeser {{ (int) round($task->baseline_due_at->diffInDays($task->due_at, false)) }} hari</span>@endif</dd>
                <dt class="col-sm-4 teks-2 fw-normal">Selesai aktual</dt><dd class="col-sm-8">{{ $task->completed_at ? Waktu::tanggalJam($task->completed_at) : '-' }}</dd>
                <dt class="col-sm-4 teks-2 fw-normal">Waktu kerja tercatat</dt><dd class="col-sm-8 angka">{{ $menitKerja }} menit @if($task->estimated_minutes)<span class="teks-2">dari estimasi {{ $task->estimated_minutes }} menit</span>@endif</dd>
                @if ($task->scheduled_publish_at || $task->platform)
                    <dt class="col-sm-4 teks-2 fw-normal">Publikasi</dt><dd class="col-sm-8">{{ $task->platform ?? '-' }} · {{ Waktu::tanggalJam($task->scheduled_publish_at) }} · {{ Kode::label(Kode::PUBLICATION_STATUS, $task->publication_status) }}@if($task->publication_url) · <a href="{{ $task->publication_url }}" rel="noopener nofollow" target="_blank">tautan</a>@endif</dd>
                @endif
            </dl>
            {{-- Satu aksi utama sesuai status (Petunjuk Teknis 6.3). --}}
            <div class="d-flex flex-wrap gap-2 mt-3">
                @if ($task->status === 'backlog' && $boleh['status'])
                    <form method="post" action="{{ route('tasks.status', $task) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="in_progress"><button class="btn btn-primary" type="submit"><x-ikon name="send"/> Mulai</button></form>
                @elseif ($task->status === 'in_progress' && $boleh['kirim'])
                    <a class="btn btn-primary" href="#kirim-hasil"><x-ikon name="send"/> Kirim untuk Review</a>
                @elseif ($task->status === 'review' && $boleh['review'] && $pending)
                    <a class="btn btn-primary" href="#tinjau"><x-ikon name="clipboard-check"/> Terima atau revisi</a>
                @elseif ($task->status === 'review')
                    <span class="teks-2 small">Menunggu PIC atau manager meninjau hasil.</span>
                @elseif ($task->status === 'done' && $boleh['review'])
                    <form method="post" action="{{ route('tasks.status', $task) }}" data-konfirmasi="Buka kembali task ini? Tanggal selesai akan dikosongkan.">@csrf @method('PATCH')<input type="hidden" name="status" value="in_progress"><button class="btn btn-outline-secondary" type="submit"><x-ikon name="rotate-ccw"/> Buka kembali</button></form>
                @endif
            </div>
        </div>

        <div class="kartu mb-3" id="checklist">
            <div class="d-flex align-items-center justify-content-between mb-2"><h2 class="kartu-judul mb-0">Checklist</h2><span class="small teks-2 angka" data-checklist-jumlah>{{ $subSelesai }} / {{ $subTotal }}</span></div>
            @if ($subTotal)<div class="progres mb-3"><span data-checklist-progres style="width:{{ $subTotal ? round($subSelesai * 100 / $subTotal) : 0 }}%"></span></div>@endif
            <ul class="list-unstyled mb-2">
                @forelse ($task->subtasks as $s)
                    <li class="d-flex align-items-center gap-2 py-1">
                        <form method="post" action="{{ route('subtasks.toggle', $s) }}" class="d-flex align-items-center gap-2 m-0">@csrf @method('PATCH')
                            <input type="hidden" name="selesai" value="{{ $s->status === 'done' ? 0 : 1 }}">
                            <input class="form-check-input mt-0" type="checkbox" id="s-{{ $s->id }}" data-centang="{{ route('subtasks.toggle', $s) }}" @checked($s->status === 'done') @disabled(! ($boleh['status'] || $boleh['ubah']))>
                            <label for="s-{{ $s->id }}" class="{{ $s->status === 'done' ? 'text-decoration-line-through teks-2' : '' }}">{{ $s->title }}</label>
                            <noscript><button class="btn btn-sm btn-outline-secondary" type="submit">{{ $s->status === 'done' ? 'Buka' : 'Selesai' }}</button></noscript>
                        </form>
                    </li>
                @empty
                    <li class="teks-2 small">Belum ada checklist.</li>
                @endforelse
            </ul>
            @if ($boleh['ubah'])
                <form method="post" action="{{ route('tasks.subtasks.store', $task) }}" class="d-flex gap-2">@csrf
                    <label class="visually-hidden" for="sub-baru">Checklist baru</label>
                    <input id="sub-baru" name="title" class="form-control" placeholder="Tambah checklist" required maxlength="255">
                    <button class="btn btn-outline-primary" type="submit"><x-ikon name="plus"/> Tambah</button></form>
            @endif
        </div>

        @if ($boleh['kirim'] && in_array($task->status, ['backlog', 'in_progress'], true))
            <form method="post" action="{{ route('tasks.submissions.store', $task) }}" enctype="multipart/form-data" class="kartu mb-3" id="kirim-hasil">
                @csrf
                <h2 class="kartu-judul">Kirim hasil untuk review</h2>
                <x-isian nama="external_url" label="Tautan hasil" jenis="url" bantuan="Misalnya tautan Google Drive, Figma, atau halaman yang sudah jadi."/>
                <div class="mb-3"><label class="form-label" for="berkas">Atau unggah berkas</label>
                    <input class="form-control @error('berkas') is-invalid @enderror" type="file" id="berkas" name="berkas">
                    <div class="form-text">PDF, gambar, dokumen Office, ZIP, MP4, paling besar 10 MB.</div>
                    @error('berkas')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <x-isian nama="submission_note" label="Catatan singkat" jenis="textarea"/>
                <button class="btn btn-primary" type="submit"><x-ikon name="send"/> Kirim untuk Review</button>
            </form>
        @endif

        @if ($pending && $boleh['review'])
            <div class="kartu mb-3" id="tinjau">
                <h2 class="kartu-judul">Tinjau hasil dari {{ $pending->submitter?->name }}</h2>
                @include('bersama.isi-submission', ['s' => $pending])
                <div class="row g-2 mt-2">
                    <div class="col-md-4"><form method="post" action="{{ route('submissions.review', $pending) }}">@csrf<input type="hidden" name="keputusan" value="approved">
                        <button class="btn btn-success w-100" type="submit"><x-ikon name="circle-check"/> Terima, task selesai</button></form></div>
                    <div class="col-md-8"><form method="post" action="{{ route('submissions.review', $pending) }}">@csrf<input type="hidden" name="keputusan" value="revision">
                        <label class="form-label" for="rv-{{ $pending->id }}">Alasan revisi</label>
                        <textarea id="rv-{{ $pending->id }}" name="review_note" class="form-control mb-2 @error('review_note') is-invalid @enderror" rows="2" required minlength="5" placeholder="Apa yang perlu diperbaiki?">{{ old('review_note') }}</textarea>
                        @error('review_note')<div class="invalid-feedback d-block mb-2">{{ $message }}</div>@enderror
                        <button class="btn btn-outline-danger" type="submit"><x-ikon name="rotate-ccw"/> Kembalikan untuk revisi</button></form></div>
                </div>
            </div>
        @endif

        <div class="kartu mb-3">
            <h2 class="kartu-judul">Riwayat hasil</h2>
            @forelse ($task->submissions as $s)
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex flex-wrap gap-2 align-items-center"><x-lencana :kode="$s->status" :teks="Kode::label(Kode::SUBMISSION_STATUS, $s->status)"/>
                        <span class="small teks-2">{{ $s->submitter?->name }} · {{ Waktu::tanggalJam($s->submitted_at) }}</span></div>
                    @include('bersama.isi-submission', ['s' => $s])
                    @if ($s->review_note)<p class="small mb-0 mt-1"><strong>{{ $s->status === 'revision' ? 'Catatan revisi' : 'Catatan review' }} ({{ $s->reviewer?->name }}):</strong> {{ $s->review_note }}</p>@endif
                </div>
            @empty
                <p class="teks-2 small mb-0">Belum ada hasil yang dikirim.</p>
            @endforelse
        </div>
    </div>

    <div class="col-lg-4">
        @if ($boleh['status'])
            <form method="post" action="{{ route('tasks.time.store', $task) }}" class="kartu mb-3">@csrf
                <h2 class="kartu-judul">Catat waktu kerja</h2>
                <input type="hidden" name="work_date" value="{{ now(Waktu::zona())->toDateString() }}">
                <div class="d-flex flex-wrap gap-2">@foreach ([15, 30, 60, 120, 240] as $m)
                    <button class="btn btn-sm btn-outline-primary" type="submit" name="minutes_spent" value="{{ $m }}">{{ $m < 60 ? $m.' menit' : ($m / 60).' jam' }}</button>@endforeach</div>
                <p class="small teks-2 mt-2 mb-0">Dicatat untuk hari ini.</p>
            </form>
        @endif

        @if ($boleh['assign'])
            <form method="post" action="{{ route('tasks.assignees', $task) }}" class="kartu mb-3">@csrf @method('PUT')
                <h2 class="kartu-judul">PIC dan pelaksana</h2>
                <x-isian nama="pic_user_id" label="PIC" jenis="select" :pilihan="$anggotaPilihan" :nilai="$task->pic_user_id" wajib/>
                <fieldset class="mb-2"><legend class="form-label">Pelaksana</legend>
                    @foreach ($anggotaPilihan as $id => $nama)
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="assignees[]" value="{{ $id }}" id="as-{{ $id }}" @checked($task->assignees->contains('id', $id))>
                            <label class="form-check-label" for="as-{{ $id }}">{{ $nama }}</label></div>
                    @endforeach
                    @error('assignees')<div class="text-danger small">{{ $message }}</div>@enderror
                </fieldset>
                <button class="btn btn-outline-primary btn-sm" type="submit">Simpan pelaksana</button>
            </form>
        @endif

        @if ($task->status !== 'done' && ($boleh['ubah'] || $boleh['status']))
            <form method="post" action="{{ route('schedule-changes.store') }}" class="kartu mb-3">@csrf
                <h2 class="kartu-judul">Ajukan perubahan deadline</h2>
                <input type="hidden" name="project_id" value="{{ $task->project_id }}"><input type="hidden" name="task_id" value="{{ $task->id }}">
                <x-isian nama="proposed_due_at" label="Deadline usulan" jenis="datetime-local" wajib/>
                <x-isian nama="reason" label="Alasan" jenis="textarea" wajib bantuan="Disetujui PIC berizin atau manager. Target awal tetap tercatat."/>
                <button class="btn btn-outline-primary btn-sm" type="submit">Ajukan</button>
            </form>
        @endif
        @if ($jadwal->isNotEmpty())
            <div class="kartu mb-3"><h2 class="kartu-judul">Perubahan jadwal</h2>
                @foreach ($jadwal as $j)
                    <div class="small border-bottom pb-2 mb-2"><x-lencana :kode="$j->status" :teks="Kode::label(Kode::SCHEDULE_STATUS, $j->status)"/>
                        <div>{{ Waktu::tanggal($j->previous_due_at) }} menjadi {{ Waktu::tanggal($j->proposed_due_at) }}</div>
                        <div class="teks-2">{{ $j->reason }} · {{ $j->requester?->name }}</div></div>
                @endforeach</div>
        @endif

        @if ($boleh['ubah'])
            <details class="kartu mb-3"><summary class="fw-semibold">Ubah isi task</summary>
                <form method="post" action="{{ route('tasks.update', $task) }}" class="mt-3">@csrf @method('PUT')
                    <x-isian nama="title" label="Judul" :nilai="$task->title" wajib/>
                    <x-isian nama="description" label="Deskripsi" jenis="textarea" :nilai="$task->description"/>
                    <x-isian nama="priority" label="Prioritas" jenis="select" :pilihan="Kode::PRIORITY" :nilai="$task->priority" wajib/>
                    <x-isian nama="task_type" label="Jenis" jenis="select" :pilihan="Kode::TASK_TYPE" :nilai="$task->task_type" wajib/>
                    <x-isian nama="estimated_minutes" label="Estimasi (menit)" jenis="number" :nilai="$task->estimated_minutes"/>
                    @unless ($task->baseline_due_at)<x-isian nama="due_at" label="Deadline" jenis="datetime-local" :nilai="Waktu::untukInput($task->due_at)" bantuan="Setelah diisi, deadline menjadi target awal dan hanya bisa digeser lewat pengajuan."/>@endunless
                    <x-isian nama="platform" label="Platform publikasi" :nilai="$task->platform" bantuan="Untuk task konten, misalnya Instagram."/>
                    <x-isian nama="scheduled_publish_at" label="Jadwal publikasi" jenis="datetime-local" :nilai="Waktu::untukInput($task->scheduled_publish_at)"/>
                    <x-isian nama="published_at" label="Tanggal terbit" jenis="datetime-local" :nilai="Waktu::untukInput($task->published_at)"/>
                    <x-isian nama="publication_status" label="Status publikasi" jenis="select" :pilihan="Kode::PUBLICATION_STATUS" :nilai="$task->publication_status" kosong="Bukan konten"/>
                    <x-isian nama="publication_url" label="Tautan publikasi" jenis="url" :nilai="$task->publication_url"/>
                    <button class="btn btn-primary btn-sm" type="submit">Simpan</button>
                </form>
                @if ($boleh['hapus'])
                    <form method="post" action="{{ route('tasks.destroy', $task) }}" class="mt-3" data-konfirmasi="Hapus task {{ $task->title }}? Task disimpan sebagai terhapus dan bisa dipulihkan.">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm" type="submit"><x-ikon name="trash-2"/> Hapus task</button></form>
                @endif
            </details>
        @endif

        <div class="kartu">
            <h2 class="kartu-judul">Riwayat</h2>
            <ol class="list-unstyled small mb-0">
                @foreach ($task->activities as $a)
                    <li class="mb-2"><div>{{ $a->description }}</div><div class="teks-2">{{ $a->user?->name }} · {{ Waktu::tanggalJam($a->created_at) }}</div></li>
                @endforeach
            </ol>
        </div>
    </div>
</div>
