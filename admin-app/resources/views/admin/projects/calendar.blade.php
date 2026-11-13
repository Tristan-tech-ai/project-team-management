<x-layouts.admin :title="'Kalender '.$project->name">
    @include('admin.projects._header')
    @include('shared.calendar', ['projectId' => $project->id])
</x-layouts.admin>
