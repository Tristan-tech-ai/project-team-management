@php use App\Support\Codes; use App\Support\Dates; $svc = app(\App\Services\ScheduleChangeService::class); @endphp
{{-- Project timeline (Technical Guide 7.3 prototype): finished tasks, meetings, visits, revisions, blockers, and deadline requests;
     add-activity form and schedule condition on the side. --}}
<x-layouts.admin :title="'Aktivitas '.$project->name">
    @include('admin.projects._header')
    <p class="muted mb-3">Riwayat rapat, kunjungan, revisi, dan penyesuaian target.</p>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="panel">
                <h2 class="panel-title">Timeline: {{ $project->name }}</h2>
                <ul class="timeline">
                    @forelse($timeline as $item)
                        @if($item->kind === 'task_done')
                            @php($t = $item->model)
                            <li class="done">
                                <div class="small muted">{{ Dates::dateTime($t->completed_at) }}</div>
                                <div class="fw-semibold">Task: <a href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a> selesai</div>
                                <div class="small muted">Task selesai dan telah diterima {{ $t->completer?->name ?? 'reviewer' }}.</div>
                            </li>
                        @elseif($item->kind === 'activity')
                            @php($a = $item->model)
                            <li class="{{ $a->impact_type === 'blocked' ? 'blocked' : ($a->impact_type !== 'none' ? 'impact' : '') }}">
                                <div class="small muted">{{ Dates::dateTime($a->occurred_at ?? $a->scheduled_at) }}@if($a->duration_minutes !== null) · {{ $a->duration_minutes }} menit @endif · dicatat {{ $a->creator?->name }}</div>
                                <div class="d-flex flex-wrap gap-2 align-items-center"><span class="fw-semibold">{{ Codes::label(Codes::ACTIVITY_TYPE, $a->activity_type) }}: {{ $a->title }}</span>
                                    <x-badge :code="$a->status === 'done' ? 'done' : ($a->status === 'cancelled' ? 'cancelled' : 'planned')" :text="Codes::label(Codes::ACTIVITY_STATUS, $a->status)"/>
                                    @if($a->impact_type !== 'none')<x-badge :code="$a->impact_type === 'blocked' ? 'blocked' : 'risk'" :text="Codes::label(Codes::IMPACT_TYPE, $a->impact_type)"/>@endif</div>
                                <div class="small muted">Peserta: {{ $a->participants->pluck('name')->implode(', ') ?: '-' }}@if($a->task) · task {{ $a->task->title }}@endif</div>
                                @if($a->description)<div class="small mt-1">{{ $a->description }}</div>@endif
                                @if($a->impact_notes)<div class="small mt-1"><strong>Dampak:</strong> {{ $a->impact_notes }}</div>@endif
                            </li>
                        @else
                            @php($j = $item->model)
                            <li class="schedule">
                                <div class="small muted">{{ Dates::dateTime($j->requested_at) }} · diajukan {{ $j->requester?->name }}</div>
                                <div class="d-flex flex-wrap gap-2 align-items-center"><span class="fw-semibold">Usulan perubahan deadline{{ $j->task ? ': '.$j->task->title : '' }}</span>
                                    <x-badge :code="$j->status" :text="Codes::label(Codes::SCHEDULE_STATUS, $j->status)"/></div>
                                @php($baseline = $j->task?->baseline_due_at ?? $project->baseline_due_at)
                                <div class="small">@if($baseline || $j->previous_due_at)Target awal <strong>{{ Dates::date($baseline) }}</strong> · sebelumnya {{ Dates::date($j->previous_due_at) }}@else Sebelumnya belum ada deadline @endif → usulan <strong>{{ Dates::date($j->proposed_due_at) }}</strong></div>
                                <div class="small muted">{{ $j->reason }}@if($j->activity) · terkait {{ $j->activity->title }}@endif</div>
                                @if($j->status !== 'pending')<div class="small muted">Diputuskan {{ $j->decider?->name }} {{ Dates::dateTime($j->decided_at) }}@if($j->decision_note): {{ $j->decision_note }}@endif</div>@endif
                                @if($j->status === 'pending' && $svc->canDecide(auth()->user(), $j))
                                    <div class="d-flex flex-wrap gap-2 mt-2">
                                        <form method="post" action="{{ route('schedule-changes.decision', $j) }}">@csrf<input type="hidden" name="decision" value="approved"><button class="btn btn-sm btn-success" type="submit">Setujui deadline baru</button></form>
                                        <form method="post" action="{{ route('schedule-changes.decision', $j) }}" class="d-flex gap-2">@csrf<input type="hidden" name="decision" value="rejected">
                                            <label class="visually-hidden" for="reject-{{ $j->id }}">Alasan penolakan</label><input id="reject-{{ $j->id }}" name="decision_note" class="form-control form-control-sm" placeholder="Alasan penolakan" required minlength="5">
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Tolak</button></form>
                                    </div>
                                @endif
                            </li>
                        @endif
                    @empty
                        <li class="small muted">Belum ada aktivitas, task selesai, atau pengajuan jadwal.</li>
                    @endforelse
                </ul>
                {{ $timeline->links() }}
            </div>
        </div>
        <div class="col-lg-4">
            @can('createActivity', $project)
                @include('shared.activity-form', ['project' => $project, 'taskOptions' => $taskOptions, 'memberOptions' => $memberOptions])
            @endcan
            <div class="panel mt-3">
                <h2 class="panel-title">Kondisi Jadwal</h2>
                <dl class="row mb-2 small">
                    <dt class="col-6 muted fw-normal">Target awal</dt><dd class="col-6 mb-1">{{ Dates::date($condition->baseline) }}</dd>
                    <dt class="col-6 muted fw-normal">Target baru</dt><dd class="col-6 mb-1">{{ Dates::date($condition->current) }}</dd>
                </dl>
                @if($condition->status === 'pending')
                    <x-badge code="pending" :text="$condition->pending > 1 ? $condition->pending.' pengajuan menunggu persetujuan' : 'Menunggu persetujuan'"/>
                @elseif($condition->status === 'shifted')
                    <x-badge code="risk" :text="'Bergeser '.abs($condition->shift_days).' hari dari target awal'"/>
                @else
                    <x-badge code="ok" text="Sesuai target awal"/>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin>
