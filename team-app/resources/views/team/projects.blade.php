@php use App\Support\Codes; use App\Support\Dates; @endphp
<x-layouts.team title="Project Saya">
    <div class="page-header"><div><h1 class="mb-0">Project Saya</h1><p>Project tempat kamu menjadi anggota.</p></div></div>
    <form method="get" class="mb-3" role="search"><label class="visually-hidden" for="keyword">Cari project</label>
        <div class="d-flex gap-2"><input id="keyword" type="search" name="keyword" value="{{ request('keyword') }}" class="form-control" placeholder="Cari project"><button class="btn btn-outline-secondary" type="submit"><x-icon name="search"/><span class="visually-hidden">Cari</span></button></div></form>
    <div class="task-list">
        @forelse($projects as $p)
            @php($percent = $p->main_tasks ? round($p->done_tasks * 100 / $p->main_tasks) : 0)
            <div class="panel">
                <div class="d-flex justify-content-between gap-2"><div class="small muted">{{ $p->client?->name ?? 'Internal' }} · {{ Codes::label(Codes::PROJECT_TYPE, $p->project_type) }}</div><x-badge :code="$p->status" :text="Codes::label(Codes::PROJECT_STATUS, $p->status)"/></div>
                <a class="fw-semibold fs-6 d-block mt-1" href="{{ route('projects.show', $p) }}">{{ $p->name }}</a>
                <div class="d-flex align-items-center gap-2 mt-2"><div class="meter flex-grow-1"><span style="width:{{ $percent }}%"></span></div><span class="small numeric">{{ $percent }}%</span></div>
                <div class="small muted mt-1">PIC {{ $p->pic?->name }} · deadline {{ Dates::date($p->due_at) }}</div>
                <div class="d-flex gap-2 mt-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('projects.board', $p) }}">Board</a><a class="btn btn-sm btn-outline-secondary" href="{{ route('projects.show', $p) }}">Ringkasan</a></div>
            </div>
        @empty
            <div class="panel"><p class="mb-0 muted">{{ request('keyword') ? 'Tidak ada project dengan nama itu.' : 'Kamu belum menjadi anggota project. Minta PIC atau manager menambahkanmu.' }}</p></div>
        @endforelse
    </div>
    <div class="mt-3">{{ $projects->links() }}</div>
</x-layouts.team>
