<x-layouts.admin :title="$title">
    <div class="page-header"><div><div class="crumb">Templates</div><h1>{{ $title }}</h1><p>{{ $description }}</p></div>
        @if($add)<a href="{{ $add }}" class="btn btn-primary"><x-icon name="plus"/> Tambah template</a>@endif</div>
    <div class="panel"><div class="table-responsive">
        @if($kind === 'project')
            <table class="table align-middle w-100" data-table="{{ $feed }}" data-columns="{{ json_encode([["data" => "name"], ["data" => "project_type", "searchable" => false], ["data" => "content", "searchable" => false, "orderable" => false],
                ["data" => "status", "name" => "is_active", "render" => "badge", "searchable" => false], ["data" => "actions", "render" => "actions", "searchable" => false, "orderable" => false]]) }}">
                <thead><tr><th scope="col">Template</th><th scope="col">Tipe</th><th scope="col">Task yang dihasilkan</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead></table>
        @else
            <table class="table align-middle w-100" data-table="{{ $feed }}" data-columns="{{ json_encode([["data" => "name"], ["data" => "task_type", "searchable" => false], ["data" => "content", "searchable" => false, "orderable" => false],
                ["data" => "status", "name" => "is_active", "render" => "badge", "searchable" => false], ["data" => "actions", "render" => "actions", "searchable" => false, "orderable" => false]]) }}">
                <thead><tr><th scope="col">Template</th><th scope="col">Jenis</th><th scope="col">Checklist</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Aksi</span></th></tr></thead></table>
        @endif
    </div></div>
</x-layouts.admin>
