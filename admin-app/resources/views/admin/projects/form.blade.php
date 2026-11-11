@php use App\Support\Kode; use App\Support\Waktu; $baru = ! $project->exists; @endphp
<x-layouts.admin :judul="$baru ? 'Buat project' : 'Ubah project'">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('projects.index') }}">Projects</a></div>
        <h1>{{ $baru ? 'Buat project' : 'Ubah '.$project->name }}</h1>
        <p>{{ $baru ? 'Pilih company, tipe, PIC, tim, dan template. Task dan subtask dari template dibuat sekaligus.' : 'Tipe project dan target awal tidak berubah setelah project dibuat.' }}</p></div></div>
    <form method="post" action="{{ $baru ? route('projects.store') : route('projects.update', $project) }}" data-form-project data-url-pratinjau="{{ route('projects.preview') }}">
        @csrf @unless ($baru) @method('PUT') @endunless
        <div class="row g-3">
            <div class="col-xl-8">
                <div class="kartu mb-3">
                    <h2 class="kartu-judul">Project</h2>
                    <div class="row g-2">
                        @if ($baru)
                            <div class="col-md-6"><x-isian nama="company_id" label="Company" jenis="select" :pilihan="$companies" :nilai="$companies->count() === 1 ? $companies->keys()->first() : null" kosong="Pilih company" wajib/></div>
                        @else
                            <div class="col-md-6"><div class="mb-3"><span class="form-label d-block">Company</span>{{ $project->company?->name }}</div></div>
                        @endif
                        <div class="col-md-6"><x-isian nama="client_id" label="Client" jenis="select" :pilihan="$clients" :nilai="$project->client_id" kosong="Tanpa client (project internal)"/></div>
                        <div class="col-md-8"><x-isian nama="name" label="Nama project" :nilai="$project->name" wajib/></div>
                        <div class="col-md-4"><x-isian nama="code" label="Kode" :nilai="$project->code" bantuan="Kosongkan untuk kode otomatis."/></div>
                        @if ($baru)
                            <div class="col-md-4"><x-isian nama="project_type" label="Klasifikasi" jenis="select" :pilihan="Kode::PROJECT_TYPE" :nilai="$project->project_type" wajib/></div>
                        @else
                            <div class="col-md-4"><div class="mb-3"><span class="form-label d-block">Klasifikasi</span>{{ Kode::label(Kode::PROJECT_TYPE, $project->project_type) }}</div></div>
                        @endif
                        <div class="col-md-4"><x-isian nama="status" label="Status" jenis="select" :pilihan="Kode::PROJECT_STATUS" :nilai="$project->status" wajib/></div>
                        <div class="col-md-4"><x-isian nama="priority" label="Prioritas" jenis="select" :pilihan="Kode::PRIORITY" :nilai="$project->priority" wajib/></div>
                        <div class="col-md-6"><label class="form-label" for="f-pic">PIC project <span class="text-danger" aria-hidden="true">*</span></label>
                            <select id="f-pic" name="pic_user_id" class="form-select @error('pic_user_id') is-invalid @enderror" required>
                                <option value="">Pilih PIC</option>
                                @foreach ($orang->unique('id') as $o)<option value="{{ $o->id }}" data-company="{{ $o->company_id }}" @selected((string) old('pic_user_id', $project->pic_user_id ?? auth()->id()) === (string) $o->id)>{{ $o->name }}</option>@endforeach
                            </select><div class="form-text">PIC adalah anggota tim internal dan otomatis masuk anggota project.</div>
                            @error('pic_user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                        <div class="col-md-3"><x-isian nama="start_at" label="Mulai" jenis="datetime-local" :nilai="Waktu::untukInput($project->start_at ?? now())"/></div>
                        <div class="col-md-3">
                            @if ($baru || ! $project->baseline_due_at)
                                <x-isian nama="due_at" label="Deadline" jenis="datetime-local" :nilai="Waktu::untukInput($project->due_at)"/>
                            @else
                                <div class="mb-3"><span class="form-label d-block">Deadline</span>{{ Waktu::tanggal($project->due_at) }}<div class="form-text">Target awal {{ Waktu::tanggal($project->baseline_due_at) }}. Geser lewat pengajuan di Activities.</div></div>
                            @endif
                        </div>
                        <div class="col-12" data-pengulangan @if(! $baru && $project->project_type === 'one_time') hidden @endif>
                            <div class="row g-2">
                                <div class="col-md-6"><x-isian nama="recurrence_unit" label="Periode" jenis="select" :pilihan="Kode::RECURRENCE_UNIT" :nilai="$project->recurrence_unit ?? 'month'" bantuan="Recurring dan ongoing dipantau per periode."/></div>
                                <div class="col-md-6"><x-isian nama="recurrence_interval" label="Setiap" jenis="number" :nilai="$project->recurrence_interval ?? 1" bantuan="Misalnya 1 = setiap bulan."/></div>
                            </div>
                        </div>
                        <div class="col-12"><x-isian nama="description" label="Deskripsi" jenis="textarea" :nilai="$project->description"/></div>
                    </div>
                </div>
                <div class="kartu mb-3">
                    <h2 class="kartu-judul">Tim</h2>
                    <fieldset class="mb-3"><legend class="form-label">Divisi terlibat</legend>
                        <div class="d-flex flex-wrap gap-2">@foreach ($divisions as $d)
                            <span data-company="{{ $d->company_id }}"><input type="checkbox" class="btn-check" name="divisions[]" value="{{ $d->id }}" id="dv-{{ $d->id }}" @checked(in_array($d->id, old('divisions', $timDivisi)))>
                                <label class="btn btn-sm btn-outline-primary" for="dv-{{ $d->id }}">{{ $d->name }}</label></span>@endforeach</div>
                        @error('divisions')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</fieldset>
                    <fieldset><legend class="form-label">Anggota project</legend>
                        <div class="d-flex flex-wrap gap-2">@foreach ($orang->unique('id') as $o)
                            <span data-company="{{ $o->company_id }}"><input type="checkbox" class="btn-check" name="members[]" value="{{ $o->id }}" id="mb-{{ $o->id }}" @checked(in_array($o->id, old('members', $timAnggota)))>
                                <label class="btn btn-sm btn-outline-primary" for="mb-{{ $o->id }}">{{ $o->name }}</label></span>@endforeach</div>
                        <div class="form-text">Orang yang membantu beberapa divisi tetap dihitung satu kali.</div>
                        @error('members')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</fieldset>
                </div>
            </div>
            <div class="col-xl-4">
                @if ($baru)
                    <div class="kartu mb-3">
                        <h2 class="kartu-judul">Template project</h2>
                        <x-isian nama="project_template_id" label="Template" jenis="select" :pilihan="$templates->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.Kode::label(Kode::PROJECT_TYPE, $t->project_type).')'])" :nilai="$project->project_template_id" kosong="Tanpa template"/>
                        <div data-pratinjau aria-live="polite"></div>
                    </div>
                @endif
                <div class="kartu"><x-isian nama="notes" label="Catatan" jenis="textarea" :nilai="$project->notes"/>
                    <button class="btn btn-primary w-100" type="submit">{{ $baru ? 'Buat project dan task' : 'Simpan perubahan' }}</button></div>
                @if (! $baru && auth()->user()->can('delete', $project))
                    <form method="post" action="{{ route('projects.destroy', $project) }}" class="mt-3" data-konfirmasi="Hapus project {{ $project->name }}? Project disimpan sebagai terhapus dan bisa dipulihkan.">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger w-100" type="submit"><x-ikon name="trash-2"/> Hapus project</button></form>
                @endif
            </div>
        </div>
    </form>
</x-layouts.admin>
