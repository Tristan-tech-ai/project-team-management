{{-- Tambah Activity: rapat, kunjungan, revisi, hambatan (quick form). Peserta aktual = anggota project yang benar-benar hadir. --}}
@php use App\Support\Kode; @endphp
<form method="post" action="{{ route('projects.activities.store', $project) }}" class="kartu">
    @csrf
    <h2 class="kartu-judul">Catat aktivitas</h2>
    <div class="row g-2">
        <div class="col-sm-6"><x-isian nama="activity_type" label="Jenis" jenis="select" :pilihan="Kode::ACTIVITY_TYPE" nilai="meeting" wajib/></div>
        <div class="col-sm-6"><x-isian nama="status" label="Status" jenis="select" :pilihan="Kode::ACTIVITY_STATUS" nilai="done" wajib/></div>
        <div class="col-12"><x-isian nama="title" label="Judul" wajib/></div>
        <div class="col-sm-6"><x-isian nama="occurred_at" label="Tanggal kejadian" jenis="datetime-local" :nilai="\App\Support\Waktu::untukInput(now())"/></div>
        <div class="col-sm-6"><x-isian nama="scheduled_at" label="Tanggal terjadwal" jenis="datetime-local"/></div>
        <div class="col-sm-6">
            <label class="form-label" for="dur-{{ $project->id }}">Durasi</label>
            <div class="d-flex gap-1 flex-wrap mb-1">@foreach ([15, 30, 60, 120] as $m)<button type="button" class="btn btn-sm btn-outline-secondary" data-isi-durasi="{{ $m }}" data-target="#dur-{{ $project->id }}">{{ $m < 60 ? $m.' mnt' : ($m / 60).' jam' }}</button>@endforeach</div>
            <input id="dur-{{ $project->id }}" class="form-control angka" type="number" min="0" name="duration_minutes" value="{{ old('duration_minutes') }}" placeholder="menit">
        </div>
        <div class="col-sm-6"><x-isian nama="impact_type" label="Dampak pada jadwal" jenis="select" :pilihan="Kode::IMPACT_TYPE" nilai="none" wajib/></div>
        @if (! empty($tasksPilihan) && count($tasksPilihan))
            <div class="col-12"><x-isian nama="task_id" label="Task terkait (opsional)" jenis="select" :pilihan="$tasksPilihan" kosong="Tidak ada"/></div>
        @endif
        <div class="col-12"><fieldset><legend class="form-label">Peserta yang hadir</legend>
            <div class="d-flex flex-wrap gap-2">@foreach ($anggotaPilihan as $id => $nama)
                <input type="checkbox" class="btn-check" name="participants[]" value="{{ $id }}" id="pa-{{ $project->id }}-{{ $id }}" autocomplete="off" @checked(in_array($id, old('participants', [])))>
                <label class="btn btn-sm btn-outline-primary" for="pa-{{ $project->id }}-{{ $id }}">{{ $nama }}</label>@endforeach</div>
            @error('participants.*')<div class="text-danger small">{{ $message }}</div>@enderror</fieldset></div>
        <div class="col-12"><x-isian nama="description" label="Catatan" jenis="textarea"/></div>
        <div class="col-12"><x-isian nama="impact_notes" label="Penjelasan dampak" jenis="textarea" bantuan="Mencatat rapat atau kunjungan tidak otomatis membebaskan keterlambatan."/></div>
        <div class="col-12">
            <details class="mb-2"><summary class="fw-semibold">Butuh geser deadline?</summary>
                <div class="row g-2 mt-1">
                    <div class="col-sm-6">
                        <div class="d-flex gap-1 flex-wrap mb-1" aria-label="Isi cepat deadline usulan">@foreach ([7, 14, 30] as $h)<button type="button" class="btn btn-sm btn-outline-secondary" data-isi-hari="{{ $h }}" data-target="#f-ajukan_deadline">+{{ $h }} hari</button>@endforeach</div>
                        <x-isian nama="ajukan_deadline" label="Deadline usulan" jenis="datetime-local"/></div>
                    <div class="col-sm-6"><x-isian nama="alasan_deadline" label="Alasan" bantuan="Diajukan ke PIC atau manager. Target awal tidak berubah."/></div>
                </div>
            </details>
        </div>
    </div>
    <button class="btn btn-primary" type="submit">Simpan aktivitas</button>
</form>
