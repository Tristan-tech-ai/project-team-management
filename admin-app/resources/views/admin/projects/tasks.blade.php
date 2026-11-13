@php use App\Support\Codes; @endphp
<x-layouts.admin :title="'Task '.$project->name">
    @include('admin.projects._header')
    @include('admin.tasks._filter', ['id' => 'filter-ptask', 'people' => $memberOptions])
    <div class="panel"><div class="table-responsive">
        <table class="table align-middle w-100" data-table="{{ route('projects.tasks.data', $project) }}" data-filter="#filter-ptask" data-order='[[4,"asc"]]'
            data-columns="{{ json_encode([["data" => "title"], ["data" => "pic_name", "name" => "pic.name", "orderable" => false], ["data" => "members", "searchable" => false, "orderable" => false],
                ["data" => "subtask", "render" => "progress", "searchable" => false, "orderable" => false], ["data" => "due", "searchable" => false], ["data" => "period", "searchable" => false, "orderable" => false],
                ["data" => "status_label", "name" => "status", "render" => "badge", "searchable" => false], ["data" => "actions", "render" => "actions", "searchable" => false, "orderable" => false]]) }}">
            <thead><tr><th scope="col">Task</th><th scope="col">PIC</th><th scope="col">Pelaksana</th><th scope="col">Checklist</th><th scope="col">Deadline</th><th scope="col">Periode</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead>
        </table>
    </div></div>
</x-layouts.admin>
