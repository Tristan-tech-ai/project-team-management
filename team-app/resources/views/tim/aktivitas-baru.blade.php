<x-layouts.tim judul="Tambah activity">
    <div class="kepala-halaman"><div><div class="remah"><a href="{{ route('activities.index') }}">Aktivitas Saya</a> / {{ $project->name }}</div><h1 class="mb-0">Tambah activity</h1></div></div>
    <div style="max-width:720px">@include('bersama.form-aktivitas', ['project' => $project, 'anggotaPilihan' => $anggotaPilihan, 'tasksPilihan' => $tasksPilihan])</div>
</x-layouts.tim>
