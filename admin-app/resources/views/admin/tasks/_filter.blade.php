@php use App\Support\Codes; @endphp
<form id="{{ $id }}" class="panel mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label" for="{{ $id }}-keyword">Cari judul</label><input id="{{ $id }}-keyword" type="search" name="keyword" class="form-control" value="{{ request('keyword') }}"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="{{ $id }}-status">Status</label><select id="{{ $id }}-status" name="status" class="form-select"><option value="">Semua</option>@foreach(Codes::TASK_STATUS as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="{{ $id }}-type">Tipe</label><select id="{{ $id }}-type" name="task_type" class="form-select"><option value="">Semua</option>@foreach(Codes::TASK_TYPE as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        @if(count($people))
            <div class="col-6 col-md-3"><label class="form-label" for="{{ $id }}-assignee">Pelaksana</label><select id="{{ $id }}-assignee" name="assignee" class="form-select"><option value="">Semua</option>@foreach($people as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        @endif
        <div class="col-6 col-md-2"><div class="form-check mb-2"><input type="hidden" name="overdue" value="0"><input class="form-check-input" type="checkbox" name="overdue" value="1" id="{{ $id }}-overdue" @checked(request('overdue'))><label class="form-check-label" for="{{ $id }}-overdue">Hanya terlambat</label></div></div>
    </div>
</form>
