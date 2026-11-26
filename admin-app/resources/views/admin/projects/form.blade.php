@php use App\Support\Codes; use App\Support\Dates; $new = ! $project->exists; @endphp
<x-layouts.admin :title="$new ? 'Buat project' : 'Ubah project'">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('projects.index') }}">Project</a></div>
        <h1>{{ $new ? 'Buat project' : 'Ubah '.$project->name }}</h1>
        <p>{{ $new ? 'Pilih company, tipe, PIC, tim, dan template. Task dan subtask dari template dibuat sekaligus.' : 'Tipe project dan target awal tidak berubah setelah project dibuat.' }}</p></div></div>
    <form method="post" action="{{ $new ? route('projects.store') : route('projects.update', $project) }}" data-form-project data-preview-url="{{ route('projects.preview') }}">
        @csrf @unless($new) @method('PUT') @endunless
        {{-- Grid areas: phones show Template first, then Project and Tim, then notes and the save button; from 1200 px the form is left,
             Template and notes are right (layout.css .project-form-grid). --}}
        <div class="project-form-grid @unless($new) no-template @endunless">
            <div class="pf-main">
                <div class="panel mb-3">
                    <h2 class="panel-title">Project</h2>
                    <div class="row g-2">
                        @if($new)
                            <div class="col-md-6"><x-field name="company_id" label="Company" type="select" :options="$companies" :value="$companies->count() === 1 ? $companies->keys()->first() : null" empty="Pilih company" required>
                                <x-slot:action>@can('companies.manage')<x-quick-add :url="route('companies.store')" target="#f-company_id" title="Tambah company" label="Company baru"/>@endcan</x-slot:action></x-field></div>
                        @else
                            <div class="col-md-6"><div class="mb-3"><span class="form-label d-block">Company</span>{{ $project->company?->name }}</div></div>
                        @endif
                        {{-- Client options carry their companies (empty = general); the list follows the chosen company. On edit the company is fixed. --}}
                        @php($clientCompany = $new ? null : (int) $project->company_id)
                        @php($selectedClient = (string) old('client_id', $project->client_id))
                        <div class="col-md-6"><div class="mb-3"><div class="label-row"><label class="form-label me-auto" for="f-client_id">Client</label>
                            @can('clients.manage')<x-quick-add :url="route('clients.store')" target="#f-client_id" title="Tambah client" label="Client baru" link-company="#f-company_id"/>@endcan</div>
                            <select id="f-client_id" name="client_id" class="form-select @error('client_id') is-invalid @enderror" data-client-select aria-describedby="f-client_id-b">
                                <option value="">Tanpa client (project internal)</option>
                                @foreach($clients as $c)
                                    @php($ids = $c->companies->pluck('id')->map(fn ($id) => (int) $id))
                                    @continue($clientCompany && $ids->isNotEmpty() && ! $ids->contains($clientCompany) && $selectedClient !== (string) $c->id)
                                    <option value="{{ $c->id }}" data-companies="{{ $ids->implode(',') }}" @selected($selectedClient === (string) $c->id)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            <div id="f-client_id-b" class="form-text" data-client-hint>{{ $new ? 'Client umum dan client milik company yang dipilih.' : 'Client umum dan client milik company project ini.' }}</div>
                            @error('client_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                            @cannot('clients.manage')<p class="form-text mt-n2">Client belum ada? Minta Super Admin menambahkannya di Data Master, Client.</p>@endcannot</div>
                        <div class="col-md-8"><x-field name="name" label="Nama project" :value="$project->name" required/></div>
                        <div class="col-md-4"><x-field name="code" label="Kode" :value="$project->code" data-code-from="name" :data-code-url="route('codes.suggest', ['for' => 'projects'])" :data-code-auto="old('code_auto')" help="Terisi otomatis dari nama. Boleh diganti."/></div>
                        @if($new)
                            <div class="col-md-4"><x-field name="project_type" label="Klasifikasi" type="select" :options="Codes::PROJECT_TYPE" :value="$project->project_type" required hint="Sekali jadi: selesai lalu ditutup. Berulang: task dibuat ulang tiap periode, misalnya konten bulanan. Berkelanjutan: berjalan terus dan dipantau per periode."/></div>
                        @else
                            <div class="col-md-4"><div class="mb-3"><span class="form-label d-block">Klasifikasi</span>{{ Codes::label(Codes::PROJECT_TYPE, $project->project_type) }}</div></div>
                        @endif
                        <div class="col-md-4"><x-field name="status" label="Status" type="select" :options="Codes::PROJECT_STATUS" :value="$project->status" required/></div>
                        <div class="col-md-4"><x-field name="priority" label="Prioritas" type="select" :options="Codes::PRIORITY" :value="$project->priority" required/></div>
                        <div class="col-md-4"><div class="label-row"><label class="form-label" for="f-pic">PIC project <span class="text-danger" aria-hidden="true">*</span></label><x-hint term="PIC project" text="Penanggung jawab project. PIC melihat semua task project, meninjau hasil, dan otomatis menjadi anggota project." class="me-auto"/></div>
                            <select id="f-pic" name="pic_user_id" class="form-select @error('pic_user_id') is-invalid @enderror" required>
                                <option value="">Pilih PIC</option>
                                @foreach($people->unique('id') as $o)<option value="{{ $o->id }}" data-company="{{ $o->company_id }}" @selected((string) old('pic_user_id', $project->pic_user_id ?? auth()->id()) === (string) $o->id)>{{ $o->name }}</option>@endforeach
                            </select>
                            @error('pic_user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                        <div class="col-md-4"><x-field name="start_at" label="Mulai" type="datetime-local" :value="Dates::forInput($project->start_at ?? now())"/></div>
                        <div class="col-md-4">
                            @if($new || ! $project->baseline_due_at)
                                <x-field data-quick-dates name="due_at" label="Deadline" type="datetime-local" :value="Dates::forInput($project->due_at)"/>
                            @else
                                <div class="mb-3"><span class="form-label d-block">Deadline</span>{{ Dates::date($project->due_at) }}<div class="form-text">Target awal {{ Dates::date($project->baseline_due_at) }}. Geser lewat pengajuan di Activities.</div></div>
                            @endif
                        </div>
                        <div class="col-12" data-recurrence @if(! $new && $project->project_type === 'one_time') hidden @endif>
                            <div class="row g-2">
                                <div class="col-md-6"><x-field name="recurrence_unit" label="Periode" type="select" :options="Codes::RECURRENCE_UNIT" :value="$project->recurrence_unit ?? 'month'" help="Recurring dan ongoing dipantau per periode."/></div>
                                <div class="col-md-6"><x-field name="recurrence_interval" label="Setiap" type="number" :value="$project->recurrence_interval ?? 1" help="Misalnya 1 = setiap bulan."/></div>
                            </div>
                        </div>
                        <div class="col-12"><x-rich-text name="description" label="Deskripsi" :value="$project->description"/></div>
                    </div>
                </div>
                <div class="panel mb-3">
                    <h2 class="panel-title">Tim</h2>
                    <fieldset class="mb-3"><legend class="form-label">Divisi terlibat</legend>
                        <div class="d-flex flex-wrap gap-2">@foreach($divisions as $d)
                            <span data-company="{{ $d->company_id }}"><input type="checkbox" class="btn-check" name="divisions[]" value="{{ $d->id }}" id="dv-{{ $d->id }}" @checked(in_array($d->id, old('divisions', $teamDivisions)))>
                                <label class="btn btn-sm btn-outline-primary" for="dv-{{ $d->id }}">{{ $d->name }}</label></span>@endforeach</div>
                        @error('divisions')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</fieldset>
                    <fieldset><legend class="form-label">Anggota project</legend>
                        <div class="d-flex flex-wrap gap-2" data-people-filter="span[data-company]">@foreach($people->unique('id') as $o)
                            <span data-company="{{ $o->company_id }}"><input type="checkbox" class="btn-check" name="members[]" value="{{ $o->id }}" id="mb-{{ $o->id }}" @checked(in_array($o->id, old('members', $teamMembers)))>
                                <label class="btn btn-sm btn-outline-primary" for="mb-{{ $o->id }}">{{ $o->name }}</label></span>@endforeach</div>
                        <div class="form-text">Orang yang membantu beberapa divisi tetap dihitung satu kali.</div>
                        @error('members')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</fieldset>
                </div>
            </div>
            @if($new)
                <div class="pf-template">
                    <div class="panel">
                        <h2 class="panel-title">Template project</h2>
                        <x-field name="project_template_id" label="Template" type="select" :options="$templates->mapWithKeys(fn ($t) => [$t->id => $t->name.' ('.Codes::label(Codes::PROJECT_TYPE, $t->project_type).')'])" :value="$project->project_template_id" empty="Tanpa template"/>
                        <div data-preview aria-live="polite"></div>
                    </div>
                </div>
            @endif
            <div class="pf-side">
                <div class="panel"><x-field name="notes" label="Catatan" type="textarea" :value="$project->notes"/>
                    <x-switch name="result_optional" label="Hasil task boleh kosong" :checked="$project->resultOptional()"
                        help="Untuk pekerjaan seperti maintenance atau riset: task bisa dikirim untuk review tanpa tautan atau berkas."/>
                    <button class="btn btn-primary w-100" type="submit">{{ $new ? 'Buat project dan task' : 'Simpan perubahan' }}</button></div>
                @if(! $new && auth()->user()->can('delete', $project))
                    <form method="post" action="{{ route('projects.destroy', $project) }}" class="mt-3" data-confirm="Hapus project {{ $project->name }}? Project disimpan sebagai terhapus dan bisa dipulihkan.">@csrf @method('DELETE')
                        <button class="btn btn-outline-danger w-100" type="submit"><x-icon name="trash-2"/> Hapus project</button></form>
                @endif
            </div>
        </div>
    </form>
</x-layouts.admin>
