@php use App\Support\Kode; use App\Support\Waktu; $svc = app(\App\Services\ScheduleChangeService::class); @endphp
<x-layouts.admin :judul="'Aktivitas '.$project->name">
    @include('admin.projects._kepala')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="kartu mb-3">
                <h2 class="kartu-judul">Perubahan jadwal</h2>
                @forelse ($jadwal as $j)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex flex-wrap gap-2 align-items-center"><x-lencana :kode="$j->status" :teks="Kode::label(Kode::SCHEDULE_STATUS, $j->status)"/>
                            <strong>{{ $j->task?->title ?? 'Deadline project' }}</strong></div>
                        <div class="small mt-1">Target awal <strong>{{ Waktu::tanggal($j->task?->baseline_due_at ?? $project->baseline_due_at) }}</strong> · sebelum {{ Waktu::tanggal($j->previous_due_at) }} · usulan <strong>{{ Waktu::tanggal($j->proposed_due_at) }}</strong></div>
                        <div class="small teks-2">{{ $j->reason }} · diajukan {{ $j->requester?->name }} {{ Waktu::tanggalJam($j->requested_at) }}@if($j->activity) · terkait {{ $j->activity->title }}@endif</div>
                        @if ($j->status !== 'pending')<div class="small teks-2">Diputuskan {{ $j->decider?->name }} {{ Waktu::tanggalJam($j->decided_at) }}@if($j->decision_note): {{ $j->decision_note }}@endif</div>@endif
                        @if ($j->status === 'pending' && $svc->bolehPutuskan(auth()->user(), $j))
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}">@csrf<input type="hidden" name="keputusan" value="approved"><button class="btn btn-sm btn-success" type="submit">Setujui deadline baru</button></form>
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}" class="d-flex gap-2">@csrf<input type="hidden" name="keputusan" value="rejected">
                                    <label class="visually-hidden" for="tolak-{{ $j->id }}">Alasan penolakan</label><input id="tolak-{{ $j->id }}" name="decision_note" class="form-control form-control-sm" placeholder="Alasan penolakan" required minlength="5">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button></form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="small teks-2 mb-0">Belum ada pengajuan perubahan jadwal.</p>
                @endforelse
            </div>
            <div class="kartu">
                <h2 class="kartu-judul">Timeline aktivitas</h2>
                <ul class="timeline">
                    @forelse ($aktivitas as $a)
                        <li class="{{ $a->impact_type === 'blocked' ? 'hambat' : ($a->impact_type !== 'none' ? 'dampak' : '') }}">
                            <div class="d-flex flex-wrap gap-2 align-items-center"><strong>{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</strong>
                                <x-lencana :kode="$a->status === 'done' ? 'done' : ($a->status === 'cancelled' ? 'cancelled' : 'planned')" :teks="Kode::label(Kode::ACTIVITY_STATUS, $a->status)"/>
                                @if ($a->impact_type !== 'none')<x-lencana :kode="$a->impact_type === 'blocked' ? 'blocked' : 'risk'" :teks="Kode::label(Kode::IMPACT_TYPE, $a->impact_type)"/>@endif</div>
                            <div class="small teks-2">{{ Waktu::tanggalJam($a->occurred_at ?? $a->scheduled_at) }}@if($a->duration_minutes !== null) · {{ $a->duration_minutes }} menit @endif · dicatat {{ $a->creator?->name }}@if($a->task) · task {{ $a->task->title }}@endif</div>
                            <div class="small">Peserta: {{ $a->participants->pluck('name')->implode(', ') ?: '-' }}</div>
                            @if ($a->description)<div class="small mt-1">{{ $a->description }}</div>@endif
                            @if ($a->impact_notes)<div class="small mt-1"><strong>Dampak:</strong> {{ $a->impact_notes }}</div>@endif
                        </li>
                    @empty
                        <li class="small teks-2">Belum ada aktivitas.</li>
                    @endforelse
                </ul>
                {{ $aktivitas->links() }}
            </div>
        </div>
        <div class="col-lg-5">
            @can('createActivity', $project)
                @include('bersama.form-aktivitas', ['project' => $project, 'tasksPilihan' => $tasksPilihan, 'anggotaPilihan' => $anggotaPilihan])
            @endcan
        </div>
    </div>
</x-layouts.admin>
