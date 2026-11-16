<x-layouts.admin :title="'Tim '.$project->name">
    @include('admin.projects._header')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="panel mb-3">
                <div class="row g-2 text-center mb-3">
                    <div class="col-4"><div class="small muted">Anggota unik</div><div class="kpi-value">{{ $counts['people'] }}</div></div>
                    <div class="col-4"><div class="small muted">Divisi</div><div class="kpi-value">{{ $counts['divisions'] }}</div></div>
                    <div class="col-4"><div class="small muted">Penugasan task</div><div class="kpi-value">{{ $counts['assignments'] }}</div></div>
                </div>
                <p class="small muted">Satu orang bisa membantu beberapa divisi, tetapi dihitung sekali di Anggota unik. Penugasan menghitung setiap pasangan task dan pelaksana.</p>
                <div class="table-responsive"><table class="table align-middle">
                    <thead><tr><th scope="col">Anggota</th><th scope="col">Divisi di project ini</th><th scope="col" class="numeric">Task</th></tr></thead>
                    <tbody>@foreach($members as $a)
                        <tr><td>{{ $a->name }} @if((int) $a->user_id === (int) $project->pic_user_id)<span class="pill pill-info">PIC</span>@endif<div class="small muted">{{ $a->email }}</div></td>
                            <td>{{ ($divisionsPerUser[$a->id] ?? collect())->pluck('name')->implode(', ') ?: '-' }}</td><td class="numeric">{{ $tasks[$a->user_id] ?? 0 }}</td></tr>
                    @endforeach</tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-5">
            @if($canManageTeam)
                <form method="post" action="{{ route('projects.team.update', $project) }}" class="panel">
                    @csrf @method('PUT')
                    <h2 class="panel-title">Atur tim</h2>
                    <label class="form-label" for="t-pic">PIC project</label>
                    <select id="t-pic" name="pic_user_id" class="form-select mb-3">@foreach($people->where('company_id', $project->company_id)->unique('id') as $o)<option value="{{ $o->id }}" @selected($o->id === $project->pic_user_id)>{{ $o->name }}</option>@endforeach</select>
                    <fieldset class="mb-3"><legend class="form-label">Divisi</legend><div class="d-flex flex-wrap gap-2">
                        @foreach($divisions->where('company_id', $project->company_id) as $d)<input type="checkbox" class="btn-check" name="divisions[]" value="{{ $d->id }}" id="td-{{ $d->id }}" @checked(in_array($d->id, $teamDivisions))><label class="btn btn-sm btn-outline-primary" for="td-{{ $d->id }}">{{ $d->name }}</label>@endforeach</div></fieldset>
                    <fieldset class="mb-3"><legend class="form-label">Anggota</legend><div class="d-flex flex-wrap gap-2" data-people-filter="label">
                        @foreach($people->where('company_id', $project->company_id)->unique('id') as $o)<input type="checkbox" class="btn-check" name="members[]" value="{{ $o->id }}" id="tm-{{ $o->id }}" @checked(in_array($o->id, $teamMembers))><label class="btn btn-sm btn-outline-primary" for="tm-{{ $o->id }}">{{ $o->name }}</label>@endforeach</div>
                        @error('members')<div class="text-danger small mt-1">{{ $message }}</div>@enderror</fieldset>
                    <button class="btn btn-primary" type="submit">Simpan tim</button>
                </form>
            @else
                <div class="panel"><p class="mb-0 muted">Divisi: {{ $divisionNames->implode(', ') ?: '-' }}</p></div>
            @endif
        </div>
    </div>
</x-layouts.admin>
