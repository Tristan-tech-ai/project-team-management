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

Jangan menjalankan `migrate:fresh`, `db:wipe`, atau impor SQL ulang pada database berisi data; perintah perusak itu juga diblokir di kode (`DB::prohibitDestructiveCommands`).

## Uji

- Database uji: `sh docs/alat/siapkan-db-uji.sh` (membuat `ptm_test` dengan urutan yang sama).
- Tes: `php artisan test` di masing-masing app (tes domain sama persis di keduanya).
- Uji peramban (kedua app harus berjalan): `node docs/alat/asap.mjs`, `node docs/alat/e2e-alur.mjs`.

Production: `composer install --no-dev`, `APP_ENV=production`, `APP_DEBUG=false`, `DEBUGBAR_ENABLED=false`.
