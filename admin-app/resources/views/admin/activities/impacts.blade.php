@php use App\Support\Kode; use App\Support\Waktu; @endphp
<x-layouts.admin judul="Hambatan & Perubahan">
    <div class="kepala-halaman"><div><div class="remah">Activities</div><h1>Hambatan &amp; Perubahan</h1>
        <p>Aktivitas yang berdampak pada jadwal dan pengajuan deadline. Hanya pengajuan yang disetujui mengubah deadline; target awal tetap.</p></div></div>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="kartu mb-3">
                <h2 class="kartu-judul">Menunggu persetujuan <span class="lencana lencana-review angka">{{ $menunggu->count() }}</span></h2>
                @forelse ($menunggu as $j)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="fw-semibold">{{ $j->task?->title ?? 'Deadline project' }} <span class="teks-2 fw-normal">· {{ $j->project?->name }}</span></div>
                        <div class="small">Target awal <strong>{{ Waktu::tanggal($j->task?->baseline_due_at ?? $j->project?->baseline_due_at) }}</strong> · sekarang {{ Waktu::tanggal($j->previous_due_at) }} · usulan <strong>{{ Waktu::tanggal($j->proposed_due_at) }}</strong></div>
                        <div class="small teks-2">{{ $j->reason }} · {{ $j->requester?->name }} · {{ Waktu::tanggalJam($j->requested_at) }}@if($j->activity) · aktivitas: {{ $j->activity->title }}@endif</div>
                        @if ($svc->bolehPutuskan(auth()->user(), $j))
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}">@csrf<input type="hidden" name="keputusan" value="approved"><button class="btn btn-sm btn-success" type="submit">Setujui</button></form>
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}" class="d-flex gap-2 flex-grow-1">@csrf<input type="hidden" name="keputusan" value="rejected">
                                    <label class="visually-hidden" for="t{{ $j->id }}">Alasan penolakan</label><input id="t{{ $j->id }}" name="decision_note" class="form-control form-control-sm" placeholder="Alasan penolakan" required minlength="5">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button></form>
                            </div>
                        @else
                            <div class="small teks-2 mt-1">Menunggu keputusan PIC berizin atau manager project ini.</div>
                        @endif
                    </div>
                @empty
                    <p class="small teks-2 mb-0">Tidak ada pengajuan yang menunggu.</p>
                @endforelse
            </div>
            <div class="kartu">
                <h2 class="kartu-judul">Riwayat keputusan</h2>
                <div class="table-responsive"><table class="table align-middle small">
                    <thead><tr><th scope="col">Task / project</th><th scope="col">Target awal</th><th scope="col">Sebelum</th><th scope="col">Usulan</th><th scope="col">Keputusan</th><th scope="col">Oleh</th></tr></thead>
                    <tbody>@forelse ($riwayat as $r)
                        <tr><td>{{ $r->task?->title ?? $r->project?->name }}</td><td>{{ Waktu::tanggal($r->task?->baseline_due_at ?? $r->project?->baseline_due_at) }}</td><td>{{ Waktu::tanggal($r->previous_due_at) }}</td><td>{{ Waktu::tanggal($r->proposed_due_at) }}</td>
                            <td><x-lencana :kode="$r->status" :teks="Kode::label(Kode::SCHEDULE_STATUS, $r->status)"/></td><td>{{ $r->decider?->name }}</td></tr>
                    @empty<tr><td colspan="6" class="teks-2">Belum ada.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="kartu">
                <h2 class="kartu-judul">Aktivitas berdampak</h2>
                <p class="small teks-2">Rapat atau kunjungan yang tercatat tidak otomatis membebaskan keterlambatan; keputusan jadwal tetap lewat pengajuan.</p>
                <ul class="timeline">@forelse ($berdampak as $a)
                    <li class="{{ $a->impact_type === 'blocked' ? 'hambat' : 'dampak' }}"><div class="small fw-semibold">{{ Kode::label(Kode::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                        <div class="small teks-2">{{ $a->project?->name }} · {{ Waktu::tanggal($a->occurred_at ?? $a->scheduled_at) }} · {{ Kode::label(Kode::IMPACT_TYPE, $a->impact_type) }} · {{ Kode::label(Kode::ACTIVITY_STATUS, $a->status) }}</div>
                        @if ($a->impact_notes)<div class="small">{{ $a->impact_notes }}</div>@endif</li>
                    @empty<li class="small teks-2">Belum ada aktivitas berdampak.</li>@endforelse</ul>
            </div>
        </div>
    </div>
</x-layouts.admin>
