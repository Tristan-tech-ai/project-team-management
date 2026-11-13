# Bukti performa (LV-05)

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
