@php use App\Support\Codes; use App\Support\Dates; use App\Services\ManagementMetricsService as M; @endphp
<div class="table-responsive"><table class="table align-middle">
    <thead><tr><th scope="col">Project</th><th scope="col">Tipe</th><th scope="col" style="min-width:150px">Progress</th>@if(! empty($full))<th scope="col" class="numeric">Anggota unik</th><th scope="col" class="numeric">Terlambat</th><th scope="col">Target awal</th><th scope="col">Deadline terbaru</th>@endif<th scope="col">Kondisi</th></tr></thead>
    <tbody>
    @forelse($rows as $p)
        <tr>
            <td><a class="fw-semibold" href="{{ route('projects.show', $p->id) }}">{{ $p->name }}</a><div class="small muted">{{ $p->client ?? 'Internal' }} · PIC {{ $p->pic }}</div></td>
            <td class="small text-nowrap">{{ Codes::label(Codes::PROJECT_TYPE, $p->project_type) }}</td>
            <td><div class="d-flex align-items-center gap-2"><div class="meter flex-grow-1"><span style="width:{{ $p->progress }}%"></span></div><span class="small numeric">{{ $p->progress }}%</span></div>
                <div class="small muted numeric">{{ $p->done_tasks }} / {{ $p->main_tasks }} task utama</div></td>
            @if(! empty($full))
                <td class="numeric">{{ $p->members }}</td><td class="numeric {{ $p->overdue_tasks ? 'text-danger fw-semibold' : '' }}">{{ $p->overdue_tasks }}</td>
                <td class="small">{{ Dates::date($p->baseline_due_at ? \Illuminate\Support\Carbon::parse($p->baseline_due_at) : null) }}</td>
                <td class="small">{{ Dates::date($p->due_at ? \Illuminate\Support\Carbon::parse($p->due_at) : null) }}
                    @if($p->baseline_due_at && $p->due_at && (string) $p->baseline_due_at !== (string) $p->due_at)<div class="muted">bergeser {{ (int) round(\Illuminate\Support\Carbon::parse($p->baseline_due_at)->diffInDays(\Illuminate\Support\Carbon::parse($p->due_at), false)) }} hari</div>@endif</td>
            @endif
            <td><x-badge :code="M::CONDITION_CLASSES[$p->condition] ?? 'neutral'" :text="M::CONDITIONS[$p->condition] ?? $p->condition"/></td>
        </tr>
    @empty
        <tr><td colspan="8" class="muted">Belum ada project.</td></tr>
    @endforelse
    </tbody>
</table></div>
