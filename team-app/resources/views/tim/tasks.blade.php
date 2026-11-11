@php use App\Support\Kode; @endphp
<x-layouts.tim judul="Task Saya">
    <div class="kepala-halaman"><div><h1 class="mb-0">Tugas Saya</h1><p>Task yang kamu pegang sebagai PIC atau pelaksana.</p></div>
</div>
    <div class="ringkas-status mb-3">
        @foreach (Kode::TASK_STATUS as $k => $v)
            <a class="kartu text-decoration-none text-reset {{ request('status') === $k ? 'border-primary' : '' }}" href="{{ route('tasks.index', array_filter(['status' => request('status') === $k ? null : $k, 'project_id' => request('project_id')])) }}" @if(request('status') === $k) aria-current="true" @endif>
                <div class="small teks-2">{{ $v }}</div><div class="angka-besar">{{ $hitung[$k] ?? 0 }}</div></a>
        @endforeach
    </div>
    <form class="kartu mb-3 d-flex flex-wrap gap-2 align-items-end" method="get">
        @if (request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
        <div class="flex-grow-1"><label class="form-label small" for="t-p">Project</label><select id="t-p" name="project_id" class="form-select" onchange="this.form.requestSubmit()"><option value="">Semua project</option>@foreach ($projects as $k => $v)<option value="{{ $k }}" @selected(request('project_id') == $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="flex-grow-1"><label class="form-label small" for="t-u">Urutkan</label><select id="t-u" name="urut" class="form-select" onchange="this.form.requestSubmit()"><option value="deadline">Deadline terdekat</option><option value="baru" @selected(request('urut') === 'baru')>Terbaru dibuat</option></select></div>
        <noscript><button class="btn btn-outline-secondary" type="submit">Terapkan</button></noscript>
    </form>
    <div class="daftar-task">
        @forelse ($tasks as $t)
            @include('tim._kartu', ['t' => $t])
        @empty
            <div class="kartu"><p class="mb-2">Belum ada task di sini.</p>@if (auth()->user()->hasPermission('tasks.create'))<a href="{{ route('tasks.create') }}" class="btn btn-primary btn-sm"><x-ikon name="plus"/> Tambah task</a>@endif</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $tasks->links() }}</div>
</x-layouts.tim>
