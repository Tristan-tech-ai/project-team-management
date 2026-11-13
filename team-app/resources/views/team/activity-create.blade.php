<x-layouts.team title="Tambah activity">
    <div class="page-header"><div><div class="crumb"><a href="{{ route('activities.index') }}">Aktivitas Saya</a> / {{ $project->name }}</div><h1 class="mb-0">Tambah activity</h1></div></div>
    <div style="max-width:720px">@include('shared.activity-form', ['project' => $project, 'memberOptions' => $memberOptions, 'taskOptions' => $taskOptions])</div>
</x-layouts.team>
