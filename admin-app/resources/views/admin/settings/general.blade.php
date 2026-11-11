<x-layouts.admin judul="Pengaturan Sistem">
    <div class="kepala-halaman"><div><div class="remah">System Settings</div><h1>Pengaturan Sistem</h1>
        <p>Berlaku untuk aplikasi Admin dan Tim karena keduanya membaca database yang sama.</p></div></div>
    <form method="post" action="{{ route('settings.general.update') }}" class="kartu" style="max-width:640px">
        @csrf @method('PUT')
        <x-isian nama="timezone" label="Zona waktu" jenis="select" :pilihan="$zona" :nilai="$nilai['timezone'] ?? 'Asia/Makassar'" wajib bantuan="Data disimpan dalam UTC; semua tanggal dan jam ditampilkan di zona ini."/>
        <x-isian nama="default_project_template_id" label="Template project bawaan" jenis="select" :pilihan="$templates" :nilai="$nilai['default_project_template_id'] ?? null" kosong="Tanpa template"/>
        <x-isian nama="table_page_length" label="Baris per halaman tabel" jenis="select" :pilihan="['10' => '10', '25' => '25', '50' => '50', '100' => '100']" :nilai="$nilai['table_page_length'] ?? '25'" wajib/>
        <button class="btn btn-primary" type="submit">Simpan</button>
    </form>
</x-layouts.admin>
