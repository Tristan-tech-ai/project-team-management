<x-layouts.admin :judul="'Kalender '.$project->name">
    @include('admin.projects._kepala')
    @include('bersama.kalender', ['projectId' => $project->id])
</x-layouts.admin>
