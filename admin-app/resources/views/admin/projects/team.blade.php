<x-layouts.admin :judul="'Tim '.$project->name">
    @include('admin.projects._kepala')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="kartu mb-3">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4"><div class="small teks-2">Anggota unik</div><div class="kpi-nilai">{{ $hitung['orang'] }}</div></div>
                    <div class="col-4"><div class="small teks-2">Divisi</div><div class="kpi-nilai">{{ $hitung['divisi'] }}</div></div>
                    <div class="col-4"><div class="small teks-2">Penugasan task</div><div class="kpi-nilai">{{ $hitung['penugasan'] }}</div></div>
                </div>
                <p class="small teks-2">Satu orang bisa membantu beberapa divisi, tetapi dihitung sekali di Anggota unik. Penugasan menghitung setiap pasangan task dan pelaksana.</p>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th scope="col">Anggota</th><th scope="col">Divisi di project ini</th><th scope="col" class="angka">Task</th></tr></thead>
                    <tbody>@foreach ($anggota as $a)
                        <tr><td>{{ $a->name }} @if((int) $a->user_id === (int) $project->pic_user_id)<span class="lencana lencana-info">PIC</span>@endif<div class="small teks-2">{{ $a->email }}</div></td>
                            <td>{{ ($divisiPer[$a->id] ?? collect())->pluck('name')->implode(', ') ?: '-' }}</td><td class="angka">{{ $tugas[$a->user_id] ?? 0 }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-5">
            @if ($bolehTim)
                <form method="post" action="{{ route('projects.team.update', $project) }}" class="kartu">
                    @csrf @method('PUT')
                    <h2 class="kartu-judul">Atur tim</h2>
                    <label class="form-label" for="t-pic">PIC project</label>
                    <select id="t-pic" name="pic_user_id" class="form-select mb-3">@foreach ($orang->where('company_id', $project->company_id)->unique('id') as $o)<option value="{{ $o->id }}" @selected($o->id === $project->pic_user_id)>{{ $o->name }}</option>@endforeach</select>
                    <fieldset class="mb-3"><legend class="form-label">Divisi</legend><div class="d-flex flex-wrap gap-2">
                        @foreach ($divisions->where('company_id', $project->company_id) as $d)<input type="checkbox" class="btn-check" name="divisions[]" value="{{ $d->id }}" id="td-{{ $d->id }}" @checked(in_array($d->id, $timDivisi))><label class="btn btn-sm btn-outline-primary" for="td-{{ $d->id }}">{{ $d->name }}</label>@endforeach</div></fieldset>
                    <fieldset class="mb-3"><legend class="form-label">Anggota</legend><div class="d-flex flex-wrap gap-2">
                        @foreach ($orang->where('company_id', $project->company_id)->unique('id') as $o)<input type="checkbox" class="btn-check" name="members[]" value="{{ $o->id }}" id="tm-{{ $o->id }}" @checked(in_array($o->id, $timAnggota))><label class="btn btn-sm btn-outline-primary" for="tm-{{ $o->id }}">{{ $o->name }}</label>@endforeach</div>
                        @error('members')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</fieldset>
                    <button class="btn btn-primary" type="submit">Simpan tim</button>
                </form>
            @else
                <div class="kartu"><p class="mb-0 teks-2">Divisi: {{ $divisi->implode(', ') ?: '-' }}</p></div>
            @endif
        </div>
    </div>
</x-layouts.admin>
