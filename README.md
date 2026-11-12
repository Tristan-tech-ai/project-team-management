# Project & Team Management

Dua aplikasi Laravel 13 dalam satu repositori, berbagi satu database MySQL, sesuai `docs/Petunjuk_Teknis_Project_Team_Management_Laravel.docx`:

- `admin-app`: manajemen, desktop-first (master data, project, template, monitoring, laporan, hak akses, menu).
- `team-app`: aktivitas harian tim, mobile-first (task saya, board, checklist, kirim hasil, kalender, aktivitas, profil).
- `docs`: petunjuk teknis, SQL sumber, riset (`docs/RISET.md`), alat uji (`docs/alat`), bukti uji (`docs/bukti`).

Hanya `admin-app` yang memigrasi schema. Kode domain bersama (model, policy, service, aturan status) ditulis di `admin-app` lalu disalin ke `team-app` dengan `php docs/alat/sinkron-domain.php`; tes `tests/Domain/DomainSamaTest` gagal bila salinannya berbeda.

## Persyaratan

PHP 8.3 atau lebih baru (diuji 8.4.16) dengan ekstensi pdo_mysql, mbstring, openssl, intl, fileinfo; Composer 2; Node.js 22 atau lebih baru dan npm; Docker (untuk MySQL 8.4 dan phpMyAdmin).

## Pasang (urutan Petunjuk Teknis 2.3)

1. Buat berkas `.env` di folder ini berisi `MYSQL_ROOT_PASSWORD=` dan `MYSQL_PASSWORD=` (sandi kuat, tidak di-commit), lalu `docker compose up -d`. MySQL di `127.0.0.1:33061`, phpMyAdmin di `http://127.0.0.1:8180`.
2. Di `admin-app` dan `team-app`: `composer install`, `npm install`, salin `.env.example` ke `.env`, isi `DB_PASSWORD` sama dengan `MYSQL_PASSWORD`, lalu `php artisan key:generate` (APP_KEY kedua app harus berbeda).
3. `cd admin-app && php artisan migrate` (hanya admin-app).
4. Impor `docs/project_team_management_laravel.sql` SATU KALI ke database `project_team_management` lewat phpMyAdmin (atau `node docs/alat/impor-phpmyadmin.mjs`, yang menolak bila tabel modul sudah ada).
5. Isi `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD` (minimal 12 karakter), dan untuk lokal `DEMO_SEED_PASSWORD` di `admin-app/.env`, lalu `php artisan db:seed` di admin-app.
6. `npm run build` di kedua app, lalu jalankan `php artisan serve --port=8101` (admin) dan `php artisan serve --port=8102` (tim).
7. Opsional untuk UAT (lokal saja, ditolak di production): `php artisan db:seed --class=UatSkenarioSeeder` di admin-app. Isinya tim agensi contoh dengan nama sintetis: manager `radit@example.test`, member `ayu`, `binda`, `putri`, `dika`, `raka` (semua `@example.test`, sandi = `DEMO_SEED_PASSWORD`), client SMA Harapan Bangsa dan Kopi Senja, project berulang Konten Sosial Media Kopi Senja, project berkelanjutan Maintenance Website Company Profile, dan Arsip Operasional 2025 berisi 520 task untuk uji DataTables.

README ini sudah diuji dari nol di folder dan database baru (12 November 2026) sampai login dan seluruh uji lulus; dua temuan saat itu sudah diperbaiki (lihat `docs/bukti/uji/UJI.md`).

Jangan menjalankan `migrate:fresh`, `db:wipe`, atau impor SQL ulang pada database berisi data; perintah perusak itu juga diblokir di kode (`DB::prohibitDestructiveCommands`).

## Uji

- Database uji: `sh docs/alat/siapkan-db-uji.sh` (membuat `ptm_test` dengan urutan yang sama).
- Tes: `php artisan test` di masing-masing app (tes domain sama persis di keduanya).
- Uji peramban (kedua app harus berjalan): `node docs/alat/asap.mjs`, `node docs/alat/e2e-alur.mjs`.

Production: `composer install --no-dev`, `APP_ENV=production`, `APP_DEBUG=false`, `DEBUGBAR_ENABLED=false`.

## Serah terima

| Hasil | Letak |
|---|---|
| Source dua app | `admin-app`, `team-app` |
| Migrasi dan schema | `admin-app/database/migrations`, `docs/project_team_management_laravel.sql` (diimpor satu kali) |
| Seeder | `InitialWorkspaceSeeder`, `DemoWorkspaceSeeder` (lokal), `UatSkenarioSeeder` (lokal, UAT) |
| Bukti uji semua role | `docs/bukti/uji/UJI.md` (17 area 11.2, 8 skenario 11.3, tes admin 41 dan tim 31, e2e 17 langkah, uji asap 3 role di kedua app), `docs/bukti/ui/AUDIT.md`, `docs/bukti/performa.md` |
| Dokumen UAT | `docs/UAT.pdf` |
| Video alur | `docs/video/ptm-demo.mp4` (4 menit 28 detik, rekaman aplikasi yang berjalan), cara merekam ulang di `docs/video/README.md` |
