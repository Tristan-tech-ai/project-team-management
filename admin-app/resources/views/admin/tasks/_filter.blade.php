@php use App\Support\Kode; @endphp
<form id="{{ $id }}" class="kartu mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3"><label class="form-label" for="{{ $id }}-cari">Cari judul</label><input id="{{ $id }}-cari" type="search" name="cari" class="form-control" value="{{ request('cari') }}"></div>
        <div class="col-6 col-md-2"><label class="form-label" for="{{ $id }}-status">Status</label><select id="{{ $id }}-status" name="status" class="form-select"><option value="">Semua</option>@foreach (Kode::TASK_STATUS as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="{{ $id }}-tipe">Tipe</label><select id="{{ $id }}-tipe" name="task_type" class="form-select"><option value="">Semua</option>@foreach (Kode::TASK_TYPE as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        @if (count($orang))
            <div class="col-6 col-md-3"><label class="form-label" for="{{ $id }}-orang">Pelaksana</label><select id="{{ $id }}-orang" name="assignee" class="form-select"><option value="">Semua</option>@foreach ($orang as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        @endif
        <div class="col-6 col-md-2"><div class="form-check mb-2"><input type="hidden" name="terlambat" value="0"><input class="form-check-input" type="checkbox" name="terlambat" value="1" id="{{ $id }}-telat" @checked(request('terlambat'))><label class="form-check-label" for="{{ $id }}-telat">Hanya terlambat</label></div></div>
    </div>
</form>
