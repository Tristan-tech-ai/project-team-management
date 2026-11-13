# Audit kesesuaian dengan Petunjuk Teknis

Diaudit 13 Nov 2026 kalimat per kalimat terhadap dokumen Petunjuk Teknis dari pembina (berkas .docx versi 1.0, 777 baris teks + tabel, 7 gambar prototipe; berkasnya tidak disertakan di salinan publik karena metadatanya memuat nama orang), bukan dari ringkasan. Setiap pernyataan dicocokkan ke kode dan ke tes atau uji peramban yang bisa gagal. Status: **Sesuai** (sudah benar sebelum audit), **Diperbaiki** (ditemukan saat audit atau revisi pembina, sudah diperbaiki dan diuji).

Ringkasan: 0 pernyataan belum terpenuhi. Putaran pertama (13 Nov pagi) menemukan 9 kekurangan, ditambah 5 revisi pembina dan penyeragaman bahasa Inggris untuk semua nama di kode.

**Putaran kedua (13 Nov sore)** membandingkan ketujuh gambar prototipe dengan tangkapan layar asli dan mengukur angka bagian 06 di setiap halaman. Hasilnya, dua penilaian putaran pertama ternyata salah dan sudah dikoreksi: (a) area klik 44x44 HP / 40x40 desktop sebelumnya hanya dicek pada tombol, padahal tautan, checkbox berlabel, judul lipatan, kepala tabel, kontrol kalender, paginasi, dan pegangan seret masih lebih kecil; (b) timeline 7.3 sebelumnya ditandai Catatan, padahal gambar prototipe jelas memuat kejadian task selesai dan usulan perubahan deadline. Ditemukan juga: judul kartu (H2) 16,8 px di bawah 18-24 px, kolom cari global prototipe 7.1-7.3 baru mencari task, dan detail task Tim belum punya tautan Kembali ke Task Saya (prototipe 7.4). Semuanya diperbaiki; ukuran bagian 06 kini diukur otomatis di setiap halaman oleh `docs/tools/ui-audit.mjs`.

## 01 Ringkasan dan sasaran

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 1.1 | Basis data dari SQL yang diberikan: 31 tabel modul, 3 role, 39 permission, 9 menu; users dari migrasi Laravel | Sesuai | `docs/project_team_management_laravel.sql` identik byte demi byte dengan berkas asli; `test_role_permissions_match_source_sql`, `test_dynamic_menu_follows_permissions` |
| 1.2 | Dua aplikasi Laravel dalam satu repositori, satu database MySQL, hanya Admin pemilik migrasi | Diperbaiki | `team-app` tidak punya folder `database`; `migrate`/`db:seed` di Team App ditolak (`test_team_app_has_no_migrations_seeders_or_factories`, `test_migration_and_seeder_commands_are_refused_in_terminal`) |
| 1.3 | Project dari template dan task tanpa formulir panjang | Diperbaiki | Quick Add kini juga dari halaman Tasks Admin (`/tasks/create`, `test_quick_add_is_available_from_tasks_page`); template `test_template_generates_all_tasks_and_subtasks_and_pic_becomes_member_and_assignee` |
| 1.4 | Alur Antrian, Dikerjakan, Review, Selesai | Sesuai | `TaskWorkflowService::ALLOWED`, `test_flow_start_submit_revise_then_approve` |
| 1.5 | Banyak anggota per project/task, satu PIC | Sesuai | `test_task_with_two_assignees_includes_pic` |
| 1.6 | Subtask checklist satu tingkat; lampiran dan link mudah dikirim | Diperbaiki | `test_checklist_is_one_level_and_tracks_progress`; lampiran dari Team App terbuka di Admin App (`test_submitted_files_use_shared_disk_and_download_respects_access`, e2e "berkas yang diunggah di Team App terbuka di Admin App") |
| 1.7 | Tim mencatat rapat, kunjungan, revisi, hambatan, laporan kerja | Sesuai | `Codes::ACTIVITY_TYPE`, e2e langkah 6 |
| 1.8 | Management melihat progres, beban, perubahan jadwal, alasan | Sesuai | Management Overview/Monitoring/Laporan Operasional, `test_dynamic_overdue_and_kpi_separate_unique_members_from_assignments` |
| 1.9 | Tanpa billing, payroll, chat, editor menu yang mengubah route PHP; template berulang digandakan manual per periode | Sesuai | Menu Management tidak bisa mengubah `route_name`; periode baru lewat tombol "Buka periode baru" (`test_recurring_opens_new_period_without_closing_parent_and_old_tasks_stay`) |

## 02 Arsitektur dan teknologi

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 2.1 | Struktur `admin-app/resources/views/admin`, `team-app/resources/views/team`, `admin-app/database/migrations` pemilik schema | Diperbaiki | Folder view Team App diganti dari `tim` ke `team`; semua nama file dan folder kode kini bahasa Inggris seperti contoh struktur |
| 2.2 | Admin desktop-first, Team mobile-first adaptif desktop | Sesuai | `test_bottom_nav_only_on_mobile_and_sidebar_only_on_desktop`, tangkapan 390/1366 |
| 2.3 | Tabel Admin: Yajra server-side; Team: kartu di HP, Yajra untuk daftar besar di desktop | Diperbaiki | Task Saya Team App kini punya tampilan Tabel Yajra di desktop (kartu tetap bawaan sesuai prototipe 7.5): `test_my_tasks_has_yajra_table_view_with_own_tasks_only` |
| 2.4 | Login Auth Laravel + policy di kedua app | Sesuai | `test_each_role_can_log_in_and_inactive_accounts_are_rejected`, `test_guest_is_redirected_and_member_login_succeeds` |
| 2.5 | Stack: Laravel 13, PHP 8.3+, MySQL 8, Bootstrap 5.3, Yajra 13 + DataTables 2, Debugbar dev di kedua app, SortableJS, FullCalendar, Vite | Sesuai | `composer.json` dan `package.json` kedua app (versi dikunci, `docs/RESEARCH.md`) |
| 2.6 | Urutan instalasi wajib; migrate hanya admin-app; impor SQL satu kali; Initial seeder, demo hanya local | Sesuai | README bagian Instalasi, `docs/tools/import-sql.php` menolak impor ulang, `DatabaseSeeder` |
| 2.7 | Jangan migrate:fresh/db:wipe/impor ulang; perubahan schema lewat migrasi admin-app | Sesuai | `DB::prohibitDestructiveCommands()`; tabel `audit_logs` lewat migrasi admin-app |
| 2.8 | Dua .env: nama app, DB sama, APP_KEY berbeda, cookie sesi terpisah, prefix cache berbeda; production APP_DEBUG/DEBUGBAR false | Sesuai | `.env.example` kedua app (`ptm_admin_session`/`ptm_team_session`, `ptm_admin_`/`ptm_team_`), Dockerfile |

## 03 Data dan aturan

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 3.1 | Nama tabel/kolom mengikuti SQL; tanpa client_pic; PIC adalah user internal | Sesuai | Model memakai tabel SQL; form Client tanpa PIC |
| 3.2 | Relasi company, client opsional, project (PIC, divisi, anggota unik, periode, task), task (PIC, assignee, subtask satu tingkat) | Sesuai | `tests/Domain/*` (29 tes identik di kedua app) |
| 3.3 | Status kode Inggris, label UI Indonesia; scheduled_publish_at/publication_status terpisah; baseline tidak diubah | Sesuai | `App\Support\Codes`, `test_only_approved_requests_change_deadline_and_baseline_stays` |
| 3.4 | Validasi PIC project, PIC task & assignee, divisi project, parent subtask, done/buka ulang, overdue dinamis, jadwal dalam transaksi | Sesuai | `test_assignees_must_be_project_members`, `test_project_divisions_must_belong_to_the_project_company`, `test_reopening_clears_completed_fields`, `test_dynamic_overdue_and_kpi_separate_unique_members_from_assignments` |
| 3.5 | Data scope: permission + keanggotaan; validasi ID lintas Company | Diperbaiki | Revisi pembina: filter Company/Client/PIC/pelaksana/divisi dan data master dibatasi cakupan; ubah master di luar cakupan 403 (`DataScopeTest`, 3 tes); kebocoran tabel Per divisi ditemukan tes dan diperbaiki |
| 3.6 | Delete dengan konfirmasi, SoftDeletes bila ada deleted_at | Sesuai | Modal konfirmasi; data tanpa deleted_at dinonaktifkan |
| 3.7 | Progress project = task utama selesai / task utama; progress task = subtask selesai / subtask | Sesuai | `test_project_progress_excludes_subtasks_from_denominator` |

## 04 Hak akses

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 4.1 | Super Admin: semua data; tidak melewati autentikasi; **semua aksi dicatat** | Diperbaiki | Tabel `audit_logs`, middleware `LogUserActions` di kedua app, halaman Log Aktivitas (`AuditLogTest`, `TeamAuditTest`) |
| 4.2 | Manager sesuai scope, tidak ubah role/menu atau data di luar scope | Diperbaiki | `DataScopeTest`; role Super Admin hanya bisa diberikan Super Admin |
| 4.3 | Member: project keanggotaan, tidak melihat project asing, tidak menyetujui deadline | Sesuai | `test_member_cannot_see_foreign_projects_and_manager_sees_own_company`, `test_member_cannot_approve_deadline_without_explicit_permission` |
| 4.4 | Matriks 9 baris (CRUD master: Manager lihat sesuai izin; task project sendiri; review; activity; management & export) | Sesuai | `test_manager_can_view_but_not_change_master_data`, `test_gate_checks_permissions_on_the_server`, smoke 3 peran |
| 4.5 | Izin diperiksa server setiap request; menu induk hanya bila ada submenu; route menu terdaftar | Sesuai | `test_inactive_menu_is_hidden_but_route_stays_protected_by_policy`, `MenuService` |

## 05 Halaman

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 5.1 | 33 halaman Admin sesuai isi tabel 5.1 | Sesuai | smoke-admin.txt: 53 halaman 3 peran tanpa galat; isi halaman dicocokkan per baris (KPI dashboard, filter tipe/status/Company/Client/PIC, overview progress/estimasi/timeline/divisi/anggota, Kanban + quick task + filter PIC/anggota, dll.) |
| 5.2 | Task List Project: Yajra + subtask progress + **quick actions** | Diperbaiki | Kolom aksi kini Mulai/Kirim hasil/Tinjau sesuai status + Buka (`test_task_list_offers_quick_actions_by_status`) |
| 5.3 | Pengaturan Sistem: timezone, default template, parameter UI | Diperbaiki | Simpan pengaturan sempat selalu galat (kolom JSON) dan baris per halaman tidak dipakai; keduanya diperbaiki (`test_settings_are_saved_and_applied`) |
| 5.4 | 14 halaman Team App sesuai tabel 5.2 | Sesuai | smoke-team.txt: 29 halaman 3 peran tanpa galat |
| 5.5 | Team "Project Saya: **project keanggotaan saja**"; detail dan Kanban konsisten termasuk lewat URL | Diperbaiki | Daftar, ringkasan, Kanban, quick add, aktivitas, review, dan kalender Team App memakai keanggotaan (`Scope::memberProjects`, `test_list_detail_and_kanban_only_show_member_projects`) |
| 5.6 | Navigasi Tim: empat tombol bawah Beranda, Tasks, Kalender, Profil; desktop tanpa bottom nav dan tanpa ruang kosong | Sesuai | `test_bottom_nav_only_on_mobile_and_sidebar_only_on_desktop`, e2e langkah 9 (padding bawah 28px) |

## 06 UI/UX

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 6.1 | Font Inter/system, ukuran body, heading, line-height, padding halaman, kartu, input, sidebar 232-260 px, bottom nav 64-72 px | Diperbaiki | Diukur di setiap halaman audit pada 390 dan 1366 px (`ui-audit.mjs`, kolom `styleIssues` di `../ui/audit-*.json` bernilai 0): body 16/15 px, line-height 1,5, H1 24/28 px, H2 18,4/20 px (judul kartu sebelumnya 16,8 px), padding halaman 16/32 px, kartu 16/24 px radius 14 px, input dan tombol 44/40 px, sidebar 248 px, bottom nav 72 px |
| 6.2 | Area klik minimal 44x44 HP dan 40x40 desktop | Diperbaiki | Semua elemen yang bisa diklik diukur di setiap halaman audit (`smallTargets` 0 di `../ui/audit-*.json`): tombol, tautan, checkbox bersama labelnya, judul lipatan, kepala tabel yang bisa diurutkan, paginasi, kontrol dan kejadian kalender, pegangan seret Kanban. Putaran pertama hanya memperbaiki tombol (`btn-sm` 36 px ke 44/40 px) |
| 6.3 | Contoh CSS: task-card radius 14, padding 16 | Diperbaiki | Kartu task kini radius 14 px, padding 16 px |
| 6.4 | Warna: biru aksi, merah terlambat, kuning review, hijau selesai; status selalu ditulis | Sesuai | Komponen badge selalu berteks, audit axe 0 pelanggaran serius |
| 6.5 | d-lg-none / d-none d-lg-flex; container maks ~1440 px; padding bawah HP dihapus di desktop | Sesuai | Layout kedua app |
| 6.6 | Quick Add dari halaman Tasks dan Board; project otomatis dari detail project | Diperbaiki | Admin Tasks kini punya Tambah Task; Board dan detail project tetap modal per project |
| 6.7 | Form awal judul, PIC, pelaksana, deadline; status Antrian | Sesuai | `QuickTaskRequest`, `test_quick_add_is_available_from_tasks_page` |
| 6.8 | Satu aksi utama per status; dropdown status di HP; checklist langsung; laporan link/file + catatan + durasi cepat; notifikasi singkat; revisi wajib catatan | Sesuai | `TaskWorkflowService::primaryAction`, e2e langkah 4-5 |
| 6.9 | Pegangan seret Kanban sebagai area klik | Diperbaiki | Sebelumnya lebih kecil dari 40 px dan ditandai catatan; kini 44/40 px seperti area klik lain (termasuk dalam pengukuran 6.2) |

## 07 Prototipe

| No | Gambar | Status | Bukti |
|---|---|---|---|
| 7.1 | Management Overview: KPI (project aktif per tipe, task berjalan, terlambat, menunggu review), monitoring project, panel Perlu Perhatian; kolom cari "Cari project, task, atau anggota" di kepala halaman (juga di 7.2 dan 7.3) | Diperbaiki | `docs/evidence/ui/admin-1366-management.png`; kolom cari sebelumnya hanya mencari task, kini halaman Pencarian berisi project, task, dan anggota sesuai cakupan data (`GlobalSearchTest`, 4 tes) |
| 7.2 | Kanban: tab project, Tambah Task, 4 kolom berhitung, kartu (anggota, subtask, deadline), catatan operasional | Sesuai | `docs/evidence/tests/e2e/01-new-board.png`, `admin/projects/board.blade.php` |
| 7.3 | Timeline aktivitas + form tambah + kondisi jadwal | Diperbaiki | Satu timeline kronologis berisi task selesai (diterima peninjau), rapat, kunjungan, revisi, hambatan, dan usulan perubahan deadline (setujui/tolak langsung di timeline), formulir Tambah aktivitas, dan kartu Kondisi Jadwal (target awal, target baru, status); halaman terakhir dibuka lebih dulu (`ProjectTimelineService`, `ProjectTimelineTest`, 2 tes). Tes ini juga menemukan galat 500 lama saat pengajuan deadline tingkat task menunggu keputusan (relasi task dimuat tanpa `project_id`), sudah diperbaiki |
| 7.4 | Tim HP: Task Saya (status, filter, kartu) dan Detail Task (kembali ke Task Saya, checklist, kirim hasil) | Diperbaiki | `docs/evidence/ui/team-390-tasks.png`, `team-390-tasks_1.png`; tautan Kembali ke Task Saya ditambahkan, checklist dengan checkbox 24 px dan baris 44 px seperti prototipe; kartu tanpa checklist bertuliskan "Tanpa checklist" |
| 7.5 | Tim desktop: sidebar, kartu lebar | Sesuai | `docs/evidence/tests/e2e/07-team-1366.png` |

## 08 Alur

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 8.1 | Buat project: company, client opsional, tipe, PIC, tanggal, divisi, anggota unik, template + preview, satu transaksi, rollback, buka Board | Sesuai | `test_preview_writes_nothing`, `test_failure_midway_rolls_back_everything`, e2e langkah 1 |
| 8.2 | Kanban: kartu berisi judul, PIC, anggota, progress, deadline, prioritas, Terlambat; seret desktop, dropdown HP; backend menjaga transisi | Sesuai | `test_rejected_kanban_status_returns_422_json_for_rollback`, e2e langkah 2 |
| 8.3 | Laporan kerja, review queue, task_activities, completed_at hanya saat done | Sesuai | `test_flow_start_submit_revise_then_approve` |
| 8.4 | Activity + peserta + durasi + dampak; schedule change dengan target lama, usulan, alasan, activity; approve PIC/Manager; baseline tetap; deviasi dan ketepatan | Sesuai | `ScheduleMetricsTest`, Monitoring Project (target awal, deadline terbaru, bergeser N hari, terlambat) |
| 8.5 | one_time ditutup setelah semua task utama selesai; recurring periode baru; ongoing per periode | Sesuai | `test_one_time_project_closes_only_after_all_main_tasks_are_done` (tes baru), `test_one_time_project_has_no_periods`, e2e langkah 8 |
| 8.6 | Mode kalender terpisah, bukan updated_at, mengikuti timezone | Sesuai | `test_calendar_modes_are_separate`, `CalendarTest` |

## 09 Implementasi

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 9.1 | Controller/Requests/Models/Policies/Services; logika identik dan tes sama di kedua app | Sesuai | 144 berkas bersama identik (`docs/tools/sync-domain.php --check`, `test_admin_and_team_domain_code_is_identical`). Catatan: nama controller aksi (`Shared\TaskActionController`, `Shared\OperationsController`) berbeda dari contoh, dibolehkan oleh kalimat "Controller berbeda sesuai kebutuhan UI" |
| 9.2 | 12 endpoint minimum dengan validasi pokok | Sesuai | `php artisan route:list`; endpoint kalender, status, toggle, submissions, review, activities, schedule-changes |
| 9.3 | Yajra: authorize, scope sebelum DataTables, eager loading, tanpa rawColumns HTML pengguna | Sesuai | `test_yajra_server_side_with_500_rows_sends_one_page_only`, `test_project_and_task_lists_have_no_n_plus_one` |
| 9.4 | Kanban menunggu API, rollback, dropdown HP endpoint sama, transaksi + task_activities | Sesuai | `resources/js/kanban.js`, e2e langkah 2 dan 4 |
| 9.5 | Debugbar dev kedua app; tanpa N+1; Yajra server-side; production flags; bukti dengan screenshot, jumlah query, catatan perbaikan | Sesuai | `docs/evidence/debugbar.txt`, `docs/evidence/performance.md`, `TESTING.md` |

## 10 Seeder

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 10.1 | InitialWorkspaceSeeder: Super Admin dari env (>= 12 karakter), COMP-A, CLIENT-A, 3 divisi, 6 jabatan, 3 template project, 3 template task + subtask, idempoten, tidak menimpa sandi | Sesuai | Isi dicocokkan dengan kode di dokumen |
| 10.2 | DemoWorkspaceSeeder hanya local: dua akun demo, DEMO-PROJECT-001, divisi, anggota, task Buat Homepage dengan dua assignee, tiga subtask | Sesuai | Isi dicocokkan; `DatabaseSeeder` mendaftarkan Initial selalu dan Demo hanya local |

## 11 Tahapan dan UAT

| No | Pernyataan | Status | Bukti |
|---|---|---|---|
| 11.1 | Lima tahap kerja | Sesuai | Semua keluaran tahap ada (login tiap role, project dari template, Kanban, operasional, management & responsive) |
| 11.2 | Checklist UAT 17 area | Sesuai | `TESTING.md` bagian checklist |
| 11.3 | Delapan skenario wajib direkam | Sesuai | e2e-flow.txt 18 langkah lulus, video `docs/video/ptm-demo.mp4` |
| 11.4 | Hasil akhir: source dua app, migrasi terkendali, seeder, bukti uji semua role, video/gambar, catatan instalasi | Sesuai | Repositori, README, `docs/evidence`, `docs/UAT.pdf` |

## Konsistensi bahasa

Semua nama di kode kini bahasa Inggris seperti contoh di petunjuk teknis: berkas dan folder, class, method, variabel, route dan URL (`/login`, `/account`, `/attachments`, `/tasks/mine`, `/calendar`, `/activities`), nama isian form, class CSS, atribut `data-*`, fungsi JavaScript, nama tes, dan komentar. Teks yang dilihat pengguna tetap bahasa Indonesia (Antrian, Dikerjakan, Review, Selesai), sesuai label UI di petunjuk teknis. Lampiran lama yang tersimpan dengan nama disk sebelumnya tetap terbaca.

Penggantian nama itu diperiksa ulang dengan tiga cara, bukan hanya dengan tes: (1) setiap string di kode dibandingkan sebelum dan sesudah penggantian; (2) rujukan silang diperiksa otomatis (setiap `id` yang dirujuk `for`, `form`, `data-filter`, `data-target`; setiap nama isian formulir terhadap kunci validasi dan parameter aturan seperti `required_without`); (3) pemindai kosakata Indonesia di nama, komentar, dan string kode (`docs/tools/release-check.py`, kini 0 temuan). Cara ini menemukan 14 kerusakan yang tidak tertangkap tes: tombol Buka periode baru menunjuk form yang namanya sudah berganti, dua filter tabel tidak tersambung, tombol geser deadline +7/+14/+30 tidak mengisi kolom, divisi anggota tidak tersimpan dari formulir, tiga aturan validasi menunjuk nama isian lama, filter cari bentrok dengan parameter `search` bawaan DataTables (galat 500), kolom angka kehilangan rata kanan, warna lencana dan token CSS lama, skrip pembuat dokumen UAT dan penyusun video tidak bisa jalan, pemeriksaan bottom navigation di audit UI tidak pernah aktif, dan skrip database uji tidak pernah mengimpor SQL. Semuanya sudah diperbaiki dan diuji ulang.
