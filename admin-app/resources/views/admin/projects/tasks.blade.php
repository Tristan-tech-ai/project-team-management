@php
    use App\Support\Codes;
    // I13: a first column with tick boxes when the user may change tasks in bulk.
    $selectColumn = $bulk ? [["data" => "bulk", "render" => "select", "searchable" => false, "orderable" => false, "cssClass" => "col-select"]] : [];
@endphp
<x-layouts.admin :title="'Task '.$project->name">
    @include('admin.projects._header')
    @include('admin.tasks._filter', ['id' => 'filter-ptask', 'people' => $memberOptions])
    <div class="panel"><div class="table-responsive">
        <table class="table align-middle w-100" id="project-task-table" data-table="{{ route('projects.tasks.data', $project) }}" data-filter="#filter-ptask" data-order='[[{{ $bulk ? 5 : 4 }},"asc"]]'
            data-table-columns="{{ json_encode([...$selectColumn, ["data" => "title", "render" => "link"], ["data" => "pic_name", "name" => "pic.name", "orderable" => false], ["data" => "members", "searchable" => false, "orderable" => false],
                ["data" => "subtask", "render" => "progress", "searchable" => false, "orderable" => false], ["data" => "due", "searchable" => false], ["data" => "period", "searchable" => false, "orderable" => false],
                ["data" => "status_label", "name" => "status", "render" => "badge", "searchable" => false], ["data" => "actions", "render" => "actions", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr>@if($bulk)<th scope="col" class="col-select"><label class="bulk-tick"><input class="form-check-input" type="checkbox" data-bulk-all aria-label="Pilih semua task di halaman ini"></label></th>@endif<th scope="col">Task</th><th scope="col">PIC</th><th scope="col">Pelaksana</th><th scope="col">Checklist</th><th scope="col">Deadline</th><th scope="col">Periode</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
    @if($bulk)@include('admin.tasks._bulk', ['bulk' => $bulk, 'table' => 'project-task-table'])@endif
</x-layouts.admin>
