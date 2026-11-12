# Project & Team Management

Sistem manajemen project dan tim untuk agensi (developer, content creator, digital marketing): **dua aplikasi Laravel 13** yang berbagi satu database MySQL.

- **Admin App** (`admin-app`, desktop-first): master data, project dari template, Kanban, review hasil, monitoring, laporan, hak akses, dan menu dinamis.
- **Team App** (`team-app`, mobile-first): task saya, papan, checklist, kirim hasil, kalender, aktivitas, dan profil.

| Admin: ringkasan | Admin: Kanban project | Tim di HP: beranda |
|---|---|---|
| ![Dashboard admin](docs/bukti/ui/admin-1366-dashboard.png) | ![Kanban](docs/bukti/ui/admin-1366-projects_7_board.png) | ![Beranda tim di HP](docs/bukti/ui/tim-390-beranda.png) |

Video alur kerja (rekaman aplikasi yang berjalan, bukan animasi): [`docs/video/ptm-demo.mp4`](docs/video/ptm-demo.mp4).

## Fitur per peran

| Peran | Yang bisa dilakukan |
|---|---|
| **Super Admin** | Semua akses: perusahaan, divisi, jabatan, anggota, client, template, pengaturan sistem, role dan permission, menu dinamis. |
| **Manager** | Membuat project (sekali, berulang, berkelanjutan) dari template dengan pratinjau task; memilih tim dari divisi; Kanban dengan aturan status di server; PIC dan banyak pelaksana per task; checklist; meninjau hasil (terima atau revisi wajib beralasan); menyetujui pengajuan geser deadline (target awal tetap tercatat); membuka periode baru tanpa menutup project induk; laporan dan monitoring. Hanya untuk perusahaan tempat ia anggota aktif. |
| **Member** | Task saya, centang checklist, kirim hasil (tautan atau berkas), kirim ulang setelah revisi, catat aktivitas dan hambatan, ajukan geser deadline, kalender. Hanya untuk project yang ia ikuti; halaman manajemen dijawab 403. |

Aturan penting yang dijaga di server, bukan hanya di tampilan:
- Task ke **Review** hanya lewat kirim hasil; ke **Selesai** hanya lewat terima oleh peninjau. Kartu yang diseret ke kolom terlarang dikembalikan.
- Deadline tidak bisa ditimpa langsung: lewat `schedule_change_requests` yang disetujui manager; baseline tetap.
- Data tanpa `deleted_at` (divisi, jabatan, anggota, keanggotaan) dinonaktifkan, tidak dihapus. Perintah perusak database (`migrate:fresh`, `db:wipe`) diblokir di kode.
- DataTables memuat data dari server (25 baris per halaman, cari dan urut di server); diuji dengan 520 task.

## Teknologi

Laravel 13 (PHP 8.3+), MySQL 8.4 (Docker) + phpMyAdmin, Bootstrap 5.3.8, jQuery 3.7.1, Yajra DataTables (datatables.net 2.3), SortableJS 1.15.7, FullCalendar 6.1.22, ikon Lucide (ISC), Laravel Debugbar (hanya pengembangan). Versi pustaka dikunci karena perintah pasang "terbaru" kini memasang versi mayor lain (catatan di `docs/RISET.md`).

## Memasang dari nol

Persyaratan: PHP 8.3 atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `intl`, `fileinfo`; Composer 2; Node.js 22+ dan npm; Docker Desktop (atau Docker Engine) untuk MySQL.

```bash
git clone https://github.com/Tristan-tech-ai/project-team-management.git
cd project-team-management
```

1. **Database.** Buat berkas `.env` di folder akar berisi dua sandi kuat (berkas ini tidak di-commit):
   ```
   MYSQL_ROOT_PASSWORD=isi-sandi-kuat-root
   MYSQL_PASSWORD=isi-sandi-kuat-aplikasi
   ```
   lalu `docker compose up -d`. MySQL ada di `127.0.0.1:33061` (database `project_team_management`, user `ptm`), phpMyAdmin di `http://127.0.0.1:8180`.
2. **Dua aplikasi.** Untuk `admin-app` dan `team-app` masing-masing:
   ```bash
   composer install
   npm install
   cp .env.example .env        # Windows PowerShell: copy .env.example .env
   php artisan key:generate    # APP_KEY kedua app harus berbeda
   ```
   Isi `DB_PASSWORD` di kedua `.env` sama dengan `MYSQL_PASSWORD`.
3. **Skema.** Hanya di `admin-app`: `php artisan migrate`. Lalu impor `docs/project_team_management_laravel.sql` **satu kali** ke database `project_team_management` lewat phpMyAdmin (menu Import), atau `node docs/alat/impor-phpmyadmin.mjs` (menolak bila tabel modul sudah ada).
4. **Data awal.** Di `admin-app/.env` isi `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD` (minimal 12 karakter), dan untuk lokal `DEMO_SEED_PASSWORD`, lalu `php artisan db:seed` di `admin-app`.
5. **Jalankan.** `npm run build` di kedua app, lalu:
   ```bash
   cd admin-app && php artisan serve --port=8101   # http://127.0.0.1:8101
   cd team-app  && php artisan serve --port=8102   # http://127.0.0.1:8102
   ```
6. **Data contoh UAT (opsional, lokal saja, ditolak di production):** `php artisan db:seed --class=UatSkenarioSeeder` di `admin-app`. Isinya tim agensi sintetis: manager `radit@example.test`, member `ayu`, `binda`, `putri`, `dika`, `raka` (semua `@example.test`, sandi = `DEMO_SEED_PASSWORD` yang kamu isi), client SMA Harapan Bangsa dan Kopi Senja, project berulang, project berkelanjutan, dan arsip 520 task.

Masuk sebagai Super Admin dengan `INITIAL_ADMIN_EMAIL` di Admin App; manager dan member masuk di Admin App atau Team App sesuai perannya.

Jangan menjalankan `migrate:fresh`, `db:wipe`, atau impor SQL ulang pada database berisi data.

**Production:** `composer install --no-dev`, `APP_ENV=production`, `APP_DEBUG=false`, `DEBUGBAR_ENABLED=false`, lalu `npm run build`.

## Struktur

```
admin-app/      Laravel: manajemen (memegang migrasi schema)
team-app/       Laravel: aplikasi tim mobile-first (tanpa migrasi)
docs/
  project_team_management_laravel.sql   schema modul (diimpor satu kali)
  RISET.md      keputusan versi dan arsitektur
  alat/         skrip uji peramban, impor, sinkron kode domain
  bukti/        hasil uji (UJI.md), audit UI, tangkapan layar, performa
  UAT.pdf       dokumen UAT
  video/        video alur dan langkah rekamannya
docker-compose.yml   MySQL 8.4 + phpMyAdmin
```

Kode domain bersama (model, policy, service, aturan status) ditulis sekali di `admin-app` lalu disalin ke `team-app` dengan `php docs/alat/sinkron-domain.php`. Tes `tests/Domain/DomainSamaTest` gagal bila salinannya berbeda.

## Uji

- Database uji: `sh docs/alat/siapkan-db-uji.sh` (membuat `ptm_test` dengan urutan pasang yang sama).
- Tes: `npm run build` dulu di kedua app (beberapa tes merender halaman yang memuat aset Vite), lalu `php artisan test` di masing-masing app (admin 41, tim 31; tes domain identik di keduanya).
- Uji peramban (kedua app berjalan): `node docs/alat/asap.mjs` (semua menu untuk 3 peran) dan `node docs/alat/e2e-alur.mjs` (alur 17 langkah).
- Hasil lengkap per skenario dan peran: `docs/bukti/uji/UJI.md`; dokumen UAT: `docs/UAT.pdf`.

README ini diuji dari nol di folder dan database baru (12 November 2026) sampai login dan seluruh uji lulus, lalu diuji lagi dari klon repositori publik ini: tes admin 41/41 dan tim 31/31 lulus.

## Catatan

Dibuat sebagai tugas PKL berdasarkan petunjuk teknis dan skema SQL dari tempat PKL. Dokumen petunjuk teknisnya tidak disertakan di repositori publik ini; skema SQL disertakan karena dibutuhkan untuk memasang. Semua nama orang dan perusahaan di data contoh adalah sintetis.
