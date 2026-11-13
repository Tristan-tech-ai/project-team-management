@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.admin title="Hambatan & Perubahan">
    <div class="page-header"><div><div class="crumb">Activities</div><h1>Hambatan &amp; Perubahan</h1>
        <p>Aktivitas yang berdampak pada jadwal dan pengajuan deadline. Hanya pengajuan yang disetujui mengubah deadline; target awal tetap.</p></div></div>
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel mb-3">
                <h2 class="panel-title">Menunggu persetujuan <span class="pill pill-review numeric">{{ $pending->count() }}</span></h2>
                @forelse($pending as $j)
                    <div class="border-bottom pb-3 mb-3">
                        <div class="fw-semibold">{{ $j->task?->title ?? 'Deadline project' }} <span class="muted fw-normal">· {{ $j->project?->name }}</span></div>
                        <div class="small">Target awal <strong>{{ Dates::date($j->task?->baseline_due_at ?? $j->project?->baseline_due_at) }}</strong> · sekarang {{ Dates::date($j->previous_due_at) }} · usulan <strong>{{ Dates::date($j->proposed_due_at) }}</strong></div>
                        <div class="small muted">{{ $j->reason }} · {{ $j->requester?->name }} · {{ Dates::dateTime($j->requested_at) }}@if($j->activity) · aktivitas: {{ $j->activity->title }}@endif</div>
                        @if($svc->canDecide(auth()->user(), $j))
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-success" type="submit">Setujui</button></form>
                                <form method="post" action="{{ route('schedule-changes.decision', $j) }}" class="d-flex gap-2 flex-grow-1">@csrf<input type="hidden" name="decision" value="rejected">
                                    <label class="visually-hidden" for="t{{ $j->id }}">Alasan penolakan</label><input id="t{{ $j->id }}" name="decision_note" class="form-control form-control-sm" placeholder="Alasan penolakan" required minlength="5">
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button></form>
                            </div>
                        @else
                            <div class="small muted mt-1">Menunggu keputusan PIC berizin atau manager project ini.</div>
                        @endif
                    </div>
                @empty
                    <p class="small muted mb-0">Tidak ada pengajuan yang menunggu.</p>
                @endforelse
            </div>
            <div class="panel">
                <h2 class="panel-title">Riwayat keputusan</h2>
                <div class="table-responsive"><table class="table align-middle small">
                    <thead><tr><th scope="col">Task / project</th><th scope="col">Target awal</th><th scope="col">Sebelum</th><th scope="col">Usulan</th><th scope="col">Keputusan</th><th scope="col">Oleh</th></tr></thead>
                    <tbody>@forelse($history as $r)
                        <tr><td>{{ $r->task?->title ?? $r->project?->name }}</td><td>{{ Dates::date($r->task?->baseline_due_at ?? $r->project?->baseline_due_at) }}</td><td>{{ Dates::date($r->previous_due_at) }}</td><td>{{ Dates::date($r->proposed_due_at) }}</td>
                            <td><x-badge :code="$r->status" :text="Codes::label(Codes::SCHEDULE_STATUS, $r->status)"/></td><td>{{ $r->decider?->name }}</td></tr>
                    @empty<tr><td colspan="6" class="muted">Belum ada.</td></tr>@endforelse</tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <h2 class="panel-title">Aktivitas berdampak</h2>
                <p class="small muted">Rapat atau kunjungan yang tercatat tidak otomatis membebaskan keterlambatan; keputusan jadwal tetap lewat pengajuan.</p>
                <ul class="timeline">@forelse($impacted as $a)
                    <li class="{{ $a->impact_type === 'blocked' ? 'blocked' : 'impact' }}"><div class="small fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</div>
                        <div class="small muted">{{ $a->project?->name }} · {{ Dates::date($a->occurred_at ?? $a->scheduled_at) }} · {{ Codes::label(Codes::IMPACT_TYPE, $a->impact_type) }} · {{ Codes::label(Codes::ACTIVITY_STATUS, $a->status) }}</div>
                        @if($a->impact_notes)<div class="small">{{ $a->impact_notes }}</div>@endif</li>
                    @empty<li class="small muted">Belum ada aktivitas berdampak.</li>@endforelse</ul>
            </div>
        </div>
    </div>
</x-layouts.admin>
