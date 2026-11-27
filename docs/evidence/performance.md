# Bukti performa (LV-05)

## Versi 2.0: desain baru dibanding desain lama

Diukur 27 Nov 2026 pada kode versi 2.0 (setelah tambahan 26 Nov dan huruf Inter) di tempat "Skala besar": salinan lokal database `ptm_modern_padat_c` (3 perusahaan, 420 project, 32.373 task) di TiDB Singapura, Admin App lokal dengan Debugbar, masuk sebagai Super Admin. Angka desain lama diukur 25 Nov 2026 dengan alat yang sama (jumlah query dari data Debugbar yang tertanam di halaman) pada worktree cabang `main` ke database yang sama.

| Halaman | Route | Query desain lama | Query versi 2.0 |
|---|---|---:|---:|
| Dashboard | `/dashboard` | 45 | 45 |
| Daftar project | `/projects` | 11 | 11 |
| Ringkasan project | `/projects/1` | 27 | 27 |
| Papan Kanban | `/projects/1/board` | 24 | 24 |
| Semua Task | `/tasks` | 11 | 11 |
| Kalender | `/calendar` | 9 | 9 |

Waktu database di lokal didominasi jarak ke TiDB (sekitar 30 ms per query dari Bali), jadi yang dibandingkan adalah jumlah query, bukan detik. Ikon kini satu berkas sprite (dirujuk dengan `<use>`), bukan SVG sebaris di setiap kartu, sehingga HTML papan yang padat lebih kecil.

Ukuran aset awal (`npm run build`, gzip tingkat 9):

| Aset | Desain lama | Versi 2.0 |
|---|---:|---:|
| CSS Admin App | 40,9 KB | 36,2 KB |
| JS Admin App | 33,6 KB | 34,0 KB |
| CSS Team App | 39,0 KB | 34,0 KB |
| JS Team App | 29,7 KB | 29,9 KB |

CSS lebih kecil karena modul Bootstrap yang tidak dipakai dibuang saat build. JS sedikit lebih besar (0,2 sampai 0,4 KB) karena pilihan tema terang/gelap, penyaring client per company, dan dropdown status kartu di HP.

## Desain lama (11 Nov 2026)

Diukur 11 Nov 2026 di lokal (PHP 8.4.16, MySQL 8.4.13 Docker, data seeder + project uji). Jumlah query dan durasi dibaca dari penyimpanan Laravel Debugbar 4.4.5 (`storage/debugbar/*.json`) untuk permintaan GET terakhir tiap halaman.

| Halaman | Route | Query | Durasi query |
|---|---|---:|---:|
| Ringkasan Sistem | `/dashboard` | 33 | 65.57ms |
| Daftar Project (halaman) | `/projects` | 11 | 71.63ms |
| Daftar Project (feed Yajra) | `/projects/data` | 12 | 37.19ms |
| Semua Task (halaman) | `/tasks` | 9 | 46.73ms |
| Semua Task (feed Yajra) | `/tasks/data` | 12 | 21.51ms |
| Task Saya (feed Yajra) | `/tasks/saya/data` | 13 | 41.25ms |
| Management Overview | `/management` | 19 | 60.98ms |
| Monitoring Project | `/management/projects` | 13 | 75.89ms |
| Monitoring Divisi & Anggota | `/management/teams` | 13 | 55.41ms |
| Anggota Tim (feed Yajra) | `/master/members/data` | 12 | 34.25ms |
| Kanban Board | `/projects/1/board` | 23 | 60.3ms |
| Detail Task | `/tasks/1` | 19 | 63.95ms |
| Event kalender | `/calendar/events` | 10 | 21.2ms |

## Tanpa N+1

`tests/Feature/AdminAccessTest::test_project_and_task_lists_have_no_n_plus_one` mengukur jumlah query feed `/projects/data` dan `/tasks/data` dengan 1 project lalu dengan 30 project (masing-masing 5 task dan subtask). Jumlah query harus sama persis; tes juga gagal bila Yajra mengembalikan galat atau data kosong. Sebelum tes ini ditulis ditemukan dua cacat: `withCount()` yang tertimpa `select('tabel.*')` sehingga progres di daftar selalu 0% (diperbaiki dengan urutan `select` lalu `withCount`, dijaga `test_progress_is_calculated_in_yajra_list`), dan pengurutan kolom yang tidak ada yang diam-diam dikembalikan Yajra sebagai JSON galat 200.

## Server-side dengan 500+ data

`test_yajra_server_side_with_500_rows_sends_one_page_only`: 520 task, permintaan `start=50 length=25` hanya mengembalikan 25 baris; `recordsTotal` 520, pencarian smart per kata `beban 51` menyaring 15 baris di server.

## Debugbar hanya lokal

Lihat `debugbar.txt`: dengan APP_DEBUG=true Debugbar tampil; dengan APP_DEBUG=false saja (APP_ENV bukan production, DEBUGBAR_ENABLED tidak diisi) tidak ada markup Debugbar dan rute `/_debugbar/open` 404. Uji pertama sempat memakai APP_ENV=production sehingga belum membuktikan APP_DEBUG=false; sudah diulang dan dikoreksi. Catatan teknis: `php artisan serve --env=<nama>` hanya memuat `.env.<nama>` di server anak bila APP_ENV di berkas itu juga `<nama>`. Paket dipasang sebagai dependensi `require-dev`.

## Ukuran aset

Bundel awal `app.js` 85 kB (gzip 26 kB); DataTables + jQuery (191 kB), SortableJS (39 kB), dan FullCalendar (200 kB) dimuat dinamis hanya di halaman yang memakainya.
