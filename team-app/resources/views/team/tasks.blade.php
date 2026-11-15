@php use App\Support\Codes; @endphp
<x-layouts.team title="Task Saya">
    <div class="page-header"><div><h1 class="mb-0">Tugas Saya</h1><p>Task yang kamu pegang sebagai PIC atau pelaksana.</p></div>
        {{-- Desktop: cards (prototype 7.5) or a Yajra table for large lists (Technical Guide 2.1 and 6.1). --}}
        <div class="btn-group d-none d-lg-inline-flex" role="group" aria-label="Tampilan daftar">
            <a class="btn btn-outline-primary {{ $table ? '' : 'active' }}" href="{{ route('tasks.index', array_filter(request()->except('view'))) }}" @unless($table) aria-current="true" @endunless>Kartu</a>
            <a class="btn btn-outline-primary {{ $table ? 'active' : '' }}" href="{{ route('tasks.index', ['view' => 'table']) }}" @if($table) aria-current="true" @endif>Tabel</a>
        </div>
</div>
    @if($table)
    <div class="d-none d-lg-block">
        <form id="filter-team" class="panel mb-3"><div class="row g-2 align-items-end">
            <div class="col-lg-3"><label class="form-label" for="ft-keyword">Cari judul</label><input id="ft-keyword" type="search" name="keyword" class="form-control"></div>
            <div class="col-lg-2"><label class="form-label" for="ft-status">Status</label><select id="ft-status" name="status" class="form-select"><option value="">Semua</option>@foreach(Codes::TASK_STATUS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-lg-4"><label class="form-label" for="ft-project">Project</label><select id="ft-project" name="project_id" class="form-select"><option value="">Semua project</option>@foreach($projects as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div class="col-lg-3"><div class="form-check mb-2"><input type="hidden" name="overdue" value="0"><input class="form-check-input" type="checkbox" name="overdue" value="1" id="ft-late"><label class="form-check-label" for="ft-late">Hanya terlambat</label></div></div>
        </div></form>
        <div class="panel"><div class="table-responsive">
            <table class="table align-middle w-100" data-table="{{ route('tasks.data') }}" data-filter="#filter-team" data-order='[[5,"asc"]]' data-table-columns="{{ json_encode([["data" => "title"], ["data" => "project_name", "name" => "project.name", "orderable" => false], ["data" => "pic_name", "name" => "pic.name", "orderable" => false], ["data" => "members", "searchable" => false, "orderable" => false], ["data" => "subtask", "render" => "progress", "searchable" => false, "orderable" => false], ["data" => "due", "searchable" => false], ["data" => "status_label", "name" => "status", "render" => "badge", "searchable" => false], ["data" => "actions", "render" => "actions", "searchable" => false, "orderable" => false]]) }}">
                <thead><tr><th scope="col">Task</th><th scope="col">Project</th><th scope="col">PIC</th><th scope="col">Pelaksana</th><th scope="col">Checklist</th><th scope="col">Deadline</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
            </table>
        </div></div>
    </div>
    @endif
    <div @class(['d-lg-none' => $table])>
    <div class="status-summary mb-3">
        @foreach(Codes::TASK_STATUS as $k => $v)
            <a class="panel text-decoration-none text-reset {{ request('status') === $k ? 'border-primary' : '' }}" href="{{ route('tasks.index', array_filter(['status' => request('status') === $k ? null : $k, 'project_id' => request('project_id')])) }}" @if(request('status') === $k) aria-current="true" @endif>
                <div class="small muted">{{ $v }}</div><div class="number-large">{{ $counts[$k] ?? 0 }}</div></a>
        @endforeach
    </div>
    <form class="panel mb-3 d-flex flex-wrap gap-2 align-items-end" method="get">
        @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div class="flex-grow-1"><label class="form-label small" for="t-p">Project</label><select id="t-p" name="project_id" class="form-select" onchange="this.form.requestSubmit()"><option value="">Semua project</option>@foreach($projects as $k => $v)<option value="{{ $k }}" @selected(request('project_id') == $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="flex-grow-1"><label class="form-label small" for="t-u">Urutkan</label><select id="t-u" name="sort" class="form-select" onchange="this.form.requestSubmit()"><option value="deadline">Deadline terdekat</option><option value="new" @selected(request('sort') === 'new')>Terbaru dibuat</option></select></div>
        <noscript><button class="btn btn-outline-secondary" type="submit">Terapkan</button></noscript>
    </form>
    <div class="task-list">
        @forelse($tasks as $t)
            @include('team._card', ['t' => $t])
        @empty
            <div class="panel"><p class="mb-2">Belum ada task di sini.</p>@if(auth()->user()->hasPermission('tasks.create'))<a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus"/> Tambah task</a>@endif</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $tasks->links() }}</div>
    </div>
</x-layouts.team>
