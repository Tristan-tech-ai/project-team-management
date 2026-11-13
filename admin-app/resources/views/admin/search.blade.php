@php use App\Support\Codes; use App\Support\Dates; @endphp
{{-- Global search (prototypes 7.1-7.3 header): projects, tasks, and members within the user's data scope. --}}
<x-layouts.admin title="Pencarian">
    <div class="page-header"><div><h1>Pencarian</h1>
        <p>{{ mb_strlen($keyword) < 2 ? 'Ketik minimal 2 huruf di kolom cari untuk mencari project, task, atau anggota.' : 'Hasil untuk "'.$keyword.'" sesuai hak aksesmu.' }}</p></div></div>
    @if(mb_strlen($keyword) >= 2)
        <div class="row g-3">
            <div class="col-xl-4">
                <div class="panel h-100">
                    <h2 class="panel-title">Project <span class="pill pill-neutral ms-1">{{ $results['projects']->count() }}</span></h2>
                    @forelse($results['projects'] as $p)
                        <div class="border-bottom py-2">
                            @can('view', $p)<a class="fw-semibold" href="{{ route('projects.show', $p) }}">{{ $p->name }}</a>@else<span class="fw-semibold">{{ $p->name }}</span>@endcan
                            <div class="small muted">{{ $p->code }} · {{ $p->client?->name ?? 'Internal' }} · PIC {{ $p->pic?->name ?? '-' }} · {{ Codes::label(Codes::PROJECT_STATUS, $p->status) }}</div>
                        </div>
                    @empty
                        <p class="small muted mb-0">Tidak ada project yang cocok.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-xl-4">
                <div class="panel h-100">
                    <h2 class="panel-title">Task <span class="pill pill-neutral ms-1">{{ $results['tasks']->count() }}</span></h2>
                    @forelse($results['tasks'] as $t)
                        <div class="border-bottom py-2">
                            <a class="fw-semibold" href="{{ route('tasks.show', $t) }}">{{ $t->title }}</a>
                            <div class="small muted">{{ $t->project?->name }} · {{ Codes::label(Codes::TASK_STATUS, $t->status) }} · deadline {{ Dates::date($t->due_at) }}</div>
                        </div>
                    @empty
                        <p class="small muted mb-0">Tidak ada task yang cocok.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-xl-4">
                <div class="panel h-100">
                    <h2 class="panel-title">Anggota <span class="pill pill-neutral ms-1">{{ $results['members']->count() }}</span></h2>
                    @forelse($results['members'] as $u)
                        <div class="border-bottom py-2">
                            @if($canManageMembers)<a class="fw-semibold" href="{{ route('members.edit', $u) }}">{{ $u->name }}</a>@else<span class="fw-semibold">{{ $u->name }}</span>@endif
                            @if($canViewMembers)<div class="small muted">{{ $u->email }}</div>@endif
                        </div>
                    @empty
                        <p class="small muted mb-0">Tidak ada anggota yang cocok.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <p class="small muted mt-3">Paling banyak {{ \App\Http\Controllers\Admin\SearchController::LIMIT }} hasil per kelompok. Untuk daftar lengkap pakai filter di halaman Projects, Tasks, atau Anggota Tim.</p>
    @endif
</x-layouts.admin>
