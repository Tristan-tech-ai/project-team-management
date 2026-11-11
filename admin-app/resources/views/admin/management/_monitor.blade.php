@php use App\Support\Kode; use App\Support\Waktu; use App\Services\ManagementMetricsService as M; @endphp
<div class="table-responsive"><table class="table align-middle">
    <thead><tr><th scope="col">Project</th><th scope="col">Tipe</th><th scope="col" style="min-width:150px">Progress</th>@if (! empty($lengkap))<th scope="col" class="angka">Anggota unik</th><th scope="col" class="angka">Terlambat</th><th scope="col">Target awal</th><th scope="col">Deadline terbaru</th>@endif<th scope="col">Kondisi</th></tr></thead>
    <tbody>
    @forelse ($baris as $p)
        <tr>
            <td><a class="fw-semibold" href="{{ route('projects.show', $p->id) }}">{{ $p->name }}</a><div class="small teks-2">{{ $p->client ?? 'Internal' }} · PIC {{ $p->pic }}</div></td>
            <td class="small">{{ Kode::label(Kode::PROJECT_TYPE, $p->project_type) }}</td>
            <td><div class="d-flex align-items-center gap-2"><div class="progres flex-grow-1"><span style="width:{{ $p->progres }}%"></span></div><span class="small angka">{{ $p->progres }}%</span></div>
                <div class="small teks-2 angka">{{ $p->task_selesai }} / {{ $p->task_utama }} task utama</div></td>
            @if (! empty($lengkap))
                <td class="angka">{{ $p->anggota }}</td><td class="angka {{ $p->task_terlambat ? 'text-danger fw-semibold' : '' }}">{{ $p->task_terlambat }}</td>
                <td class="small">{{ Waktu::tanggal($p->baseline_due_at ? \Illuminate\Support\Carbon::parse($p->baseline_due_at) : null) }}</td>
                <td class="small">{{ Waktu::tanggal($p->due_at ? \Illuminate\Support\Carbon::parse($p->due_at) : null) }}
                    @if ($p->baseline_due_at && $p->due_at && (string) $p->baseline_due_at !== (string) $p->due_at)<div class="teks-2">bergeser {{ (int) round(\Illuminate\Support\Carbon::parse($p->baseline_due_at)->diffInDays(\Illuminate\Support\Carbon::parse($p->due_at), false)) }} hari</div>@endif</td>
            @endif
            <td><x-lencana :kode="M::KONDISI_KELAS[$p->kondisi] ?? 'netral'" :teks="M::KONDISI[$p->kondisi] ?? $p->kondisi"/></td>
        </tr>
    @empty
        <tr><td colspan="8" class="teks-2">Belum ada project.</td></tr>
    @endforelse
    </tbody>
</table></div>
