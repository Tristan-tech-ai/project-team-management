<x-layouts.admin title="Pengaturan Sistem">
    <div class="page-header"><div><div class="crumb">System Settings</div><h1>Pengaturan Sistem</h1>
        <p>Berlaku untuk aplikasi Admin dan Tim karena keduanya membaca database yang sama.</p></div>
        @if(auth()->user()->isSuperAdmin())<a class="btn btn-outline-secondary" href="{{ route('settings.audit') }}">Log aktivitas</a>@endif</div>
    <form method="post" action="{{ route('settings.general.update') }}" class="panel" style="max-width:640px">
        @csrf @method('PUT')
        <x-field name="timezone" label="Zona waktu" type="select" :options="$zone" :value="$values['timezone'] ?? 'Asia/Makassar'" required help="Data disimpan dalam UTC; semua tanggal dan jam ditampilkan di zona ini."/>
        <x-field name="default_project_template_id" label="Template project bawaan" type="select" :options="$templates" :value="$values['default_project_template_id'] ?? null" empty="Tanpa template"/>
        <x-field name="table_page_length" label="Baris per halaman tabel" type="select" :options="['10' => '10', '25' => '25', '50' => '50', '100' => '100']" :value="$values['table_page_length'] ?? '25'" required/>
        <button class="btn btn-primary" type="submit">Simpan</button>
    </form>
</x-layouts.admin>
