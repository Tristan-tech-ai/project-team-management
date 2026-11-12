# Riset LV-00: Project & Team Management (Laravel)

Dibaca 11 Nov 2026 dari dua berkas sumber di `C:/Users/Tristan/Downloads/project-pkl`:
`Petunjuk_Teknis_Project_Team_Management_Laravel.docx` (versi dokumen 1.0, 11 Nov 2026, 1.015 paragraf, 7 gambar) dan
`project_team_management_laravel.sql` (928 baris). Teks docx diekstrak lengkap dari `word/document.xml`; gambar prototype dilihat satu per satu.
Semua yang ditulis di bawah adalah ringkasan sumber; keputusan sendiri ditandai **Keputusan**.

## 1. Ringkasan per bagian dokumen

### 01 Ringkasan & Sasaran
- Instruksi kerja trainee, bukan proposal. Basis data: SQL yang diberikan (31 tabel modul, 3 role, 39 permission, 9 menu utama Super Admin). Tabel `users` dari migrasi bawaan Laravel.
- Arsitektur: **dua aplikasi Laravel dalam satu repositori** (Admin = pengelolaan, Tim = aktivitas harian), **satu database MySQL**, hanya Admin pemilik migrasi schema.
- Tujuan yang terlihat pengguna: project dari template dan task tanpa formulir panjang; status Antrian, Dikerjakan, Review, Selesai; banyak anggota per project/task dengan satu PIC; subtask checklist satu tingkat; lampiran dan link hasil; catatan rapat, kunjungan, revisi, hambatan, laporan; manajemen melihat progres, beban, perubahan jadwal, alasan.
- Di luar versi pertama: billing, payroll, CRM, chat real-time, push notification, aplikasi native, integrasi iklan, editor menu yang mengubah route PHP. Template berulang **digandakan manual per periode**.

### 02 Arsitektur & Teknologi
- Struktur: `project-team-management/{admin-app, team-app, docs}`. Admin desktop-first (sidebar tetap + header, Yajra server-side). Tim mobile-first (bottom nav di HP, sidebar di desktop; kartu di HP, Yajra untuk daftar desktop besar). Keduanya Auth Laravel + Policy. Dua deployment terpisah, bukan dua tema.
- Stack wajib: Laravel 13.x, PHP 8.3+, MySQL 8.0+ utf8mb4, Bootstrap 5.3 (HTML/CSS/JS, tanpa SPA), Yajra Laravel DataTables 13.x + DataTables 2, `fruitcake/laravel-debugbar` (--dev, kedua app, lokal saja), SortableJS (Kanban), FullCalendar (deadline, aktivitas, selesai, publikasi), Vite + npm. Kanban bukan pengganti Yajra.
- Urutan instalasi wajib: DB kosong + PHP/Composer/Node; buat dua app; `.env` DB sama, `APP_KEY` berbeda; `php artisan migrate` **hanya di admin-app**; impor SQL modul **satu kali** (phpMyAdmin); `InitialWorkspaceSeeder` dari admin-app, demo hanya `local`; aset Bootstrap/DataTables/SortableJS/FullCalendar lalu Vite. Dilarang `migrate:fresh`, `db:wipe`, impor ulang pada DB berisi data; perubahan schema berikutnya lewat migrasi admin-app.
- `.env`: `APP_NAME="PTM Admin"`/`"PTM Team"`, `DB_DATABASE=project_team_management`, `SESSION_COOKIE=ptm_admin_session`/`ptm_team_session`; host lokal berbeda per app; prefix queue/cache berbeda; production `APP_DEBUG=false`, `DEBUGBAR_ENABLED=false`.

### 03 Pemetaan Database & Aturan Data
- Nama tabel/kolom persis SQL. Tidak ada `client_pic`/`client_contacts`; PIC selalu user internal.
- Relasi: Company punya divisi, jabatan, anggota, project; Client master terpisah, `client_id` boleh kosong (project internal). Project: satu PIC, banyak divisi, banyak anggota unik, periode, task. Anggota ke divisi project lewat `project_member_divisions` (dihitung unik). Task: satu PIC, banyak assignee, subtask lewat `tasks.parent_id` maksimal satu tingkat. Status kode `backlog/in_progress/review/done`. Konten memakai `scheduled_publish_at` + `publication_status` terpisah. Activity di `project_activities` + peserta aktual. Geser jadwal lewat `schedule_change_requests`; `baseline_due_at` tidak boleh diubah.
- Validasi Laravel wajib: PIC project user aktif anggota company dan masuk `project_members`; PIC task dan assignee anggota aktif project, PIC otomatis assignee; divisi project dari company project; parent subtask hanya task utama, project dan periode sama; done mengisi `completed_at`/`completed_by`, buka ulang mengosongkan; overdue dihitung dinamis; hanya permintaan approved mengubah `due_at` dalam transaksi; scope = permission + keanggotaan + validasi ID lintas company; hapus dengan konfirmasi, SoftDeletes bila ada `deleted_at`.
- Progress project = task utama selesai / task utama non-deleted dalam lingkup project/periode (subtask tidak masuk penyebut). Progress task = subtask selesai / jumlah subtask.

### 04 Level Pengguna & Hak Akses
- Role tersimpan: Super Admin (semua; tetap lewat autentikasi; semua aksi dicatat), Manager (buat project, kelola tim, review, setujui jadwal, laporan sesuai scope; tidak ubah role/menu), Member (project keanggotaan, task pribadi, subtask, hasil, activity, kalender; tidak melihat project asing, tidak menyetujui deadline).
- PIC project, PIC task, kepala divisi (`divisions.lead_user_id`), jabatan (`positions`) adalah penugasan, bukan role. Permission lewat `roles -> role_permissions -> permissions` + `user_roles`.
- Matriks aksi x role (Ya / sesuai scope / Tidak) untuk role-permission-menu, master data, project, anggota, task, kirim hasil, review/deadline, activity/kalender, management/export.
- Keamanan: Gate/Policy di server setiap request; menyembunyikan menu tidak cukup; parent menu tampil bila ada minimal satu submenu berizin; `route_name` menu wajib route terdaftar; manager tidak bisa menjalankan route arbitrer.

### 05 Daftar Halaman & Navigasi
- Admin: 9 menu dari SQL; halaman turunan: Ringkasan Sistem; Daftar/Tambah-Edit Project, Project Overview, Kanban Board, Task List, Project Team, Activities, Calendar Project; Semua Task, Task Saya, Menunggu Review, Detail Task; Kalender (mode Jadwal/Aktivitas/Publikasi); Daftar Aktivitas, Rapat & Kunjungan, Hambatan & Perubahan; Template Project/Task; Management Overview, Monitoring Project (baseline vs due; on-track/blocked/delayed), Monitoring Divisi & Anggota, Laporan Operasional (export, jejak jadwal); Company, Client, Divisi, Jabatan, Anggota Tim (Yajra CRUD); Roles & Permissions, Menu Management, Pengaturan Sistem; Profil & Password.
- Tim: Ringkasan Saya, Task Saya (kartu), Detail Task, Quick Add Task, Kirim Hasil, Project Saya, Board Project (drag di desktop, dropdown status di HP), Ringkasan Project, Kalender Saya, Aktivitas Saya, Tambah Activity, Review Task (bila berizin), Profil Saya.
- Navigasi Tim HP: empat tombol bawah Beranda, Tasks, Kalender, Profil; Projects dan Activities dari Beranda/Task. Desktop: bottom nav hilang, sidebar/toolbar, isi melebar tanpa ruang kosong bekas navbar.

### 06 Standar UI/UX & Responsive
- Font Inter / system sans; body 16 px (HP) dan 14-16 px (desktop); H1 22-24 / 26-30 px, H2 18-20 / 20-24 px; line-height 1,45-1,6; padding halaman 16 px (12 px layar kecil) / 24-32 px; gap 8/12/16/24; kartu padding 16 / 18-24, radius 12-16; input/tombol min 44-48 / 40-44 px; area klik 44x44 / 40x40; bottom nav 64-72 px; sidebar 232-260 px.
- Warna: putih, off-white, biru untuk aksi; merah terlambat, kuning perhatian/review, hijau selesai; status selalu ditulis; tanpa gradien berlebihan, font tipis, gerak tidak perlu, tabel lebar di HP.
- CSS acuan: `.app-main { padding: 16px 16px 92px }`, bottom nav 72 px, `@media (min-width: 992px)` padding 28px 32px + `margin-left: 248px`, bottom nav `display:none`; Bootstrap `d-lg-none` / `d-none d-lg-flex`; konten maks sekitar 1440 px; hapus padding-bottom mobile di desktop.
- UX anti-ribet: Quick Add dari Tasks dan Board (project otomatis terpilih dari detail project); form awal judul, PIC, pelaksana, deadline, status Antrian; satu aksi utama per status (Mulai, Kirim Review, Selesaikan/Revisi); dropdown status di layar sentuh; checklist langsung dicentang; laporan kerja = link/file + satu catatan, durasi cepat; notifikasi singkat tanpa mereset kartu/filter; konfirmasi hanya untuk aksi berisiko; revisi wajib catatan; riwayat submission tidak dihapus.

### 07 Prototype Tampilan
- Tujuh gambar (wireframe, bukan aplikasi jadi): 7.1 Management Overview (sidebar gelap navy, 4 KPI Project Aktif/Task Berjalan/Terlambat/Menunggu Review, tabel Monitoring Project dengan bar progres dan kondisi On Track/At Risk/Terlambat, panel Perlu Perhatian), 7.2 Kanban (judul project + meta Client/tipe/PIC/divisi/anggota, tab Overview/Kanban Board/Task List/Project Team/Activities/Calendar, empat kolom berhitungan, kartu judul/anggota/subtask/deadline, pita catatan operasional), 7.3 timeline aktivitas, 7.4 HP Task Saya (4 hitungan status, filter project + urut deadline, kartu project/judul/subtask/orang/progres/deadline, lencana status, bottom nav 4) dan Detail Task, 7.5 desktop Tim dengan sidebar menggantikan bottom nav.
- Ikon di gambar prototype tampil sebagai kotak pengganti; nama ikon di SQL (`layout-dashboard`, `folder-kanban`, `list-todo`, `calendar-days`, `activity`, `copy`, `chart-no-axes-combined`, `database`, `settings-2`) adalah nama ikon Lucide.

### 08 Alur Operasional
- 8.1 Buat project: Company, Client opsional, nama, `one_time/recurring/ongoing`, PIC, tanggal; divisi dan anggota unik, PIC otomatis anggota; pilih template (Website, Produksi Konten, Digital Marketing Campaign) dengan preview task/subtask; generate dalam satu transaksi lalu buka Board, gagal = rollback; PIC menambah assignee (boleh lintas divisi, wajib anggota project).
- 8.2 Kanban: kolom backlog/in_progress/review/done dengan aksi utama; kartu judul, PIC, avatar/jumlah anggota, progres subtask, deadline, prioritas, label Terlambat; SortableJS di desktop, dropdown/tombol di HP; backend menegakkan transisi (anggota tidak bisa melewati Review).
- 8.3 Laporan kerja: Mulai, centang subtask, Kirim untuk Review (link/berkas + catatan), PIC/manager Terima (Selesai) atau Revisi beralasan (Dikerjakan); `task_activities` mencatat user, waktu, perubahan; `completed_at` hanya saat done.
- 8.4 Activity: jenis rapat/kunjungan/revisi/hambatan/lainnya; judul, tanggal, peserta aktual, durasi, dampak; tertaut project dan opsional task; ubah deadline lewat `schedule_change_request` (target lama, usulan, alasan, activity); PIC berizin/Manager approve/reject; hanya approved mengubah `due_at`, baseline tetap; Management menampilkan deviasi dari target awal dan ketepatan terhadap deadline disetujui. Rapat tidak otomatis membebaskan keterlambatan.
- 8.5 Tipe: one_time ditutup setelah semua task utama diterima; recurring = project induk + `project_periods` per bulan/minggu, task digenerate per periode; ongoing tanpa akhir wajib, progres per periode.
- 8.6 Kalender: Jadwal = `due_at` + `scheduled_at`; Aktivitas Selesai = `tasks.completed_at`; Publikasi = `scheduled_publish_at`/`published_at`; bukan `updated_at`; tampilan menurut timezone konfigurasi (contoh Asia/Makassar).

### 09 Pedoman Implementasi Laravel
- Struktur: Controllers (Project, Task, TaskStatus, TaskSubmission, Activity, Management), Requests per form, Models sesuai SQL, Policies (scope + permission), Services (ProjectTemplateService, TaskWorkflowService, ScheduleChangeService, ManagementMetricsService). Logika status/progres/izin/deadline identik di kedua app dengan tes yang sama.
- Endpoint minimum: `GET/POST /projects`, `POST /projects/{id}/tasks`, `PATCH /tasks/{id}/status`, `PATCH /subtasks/{id}/toggle`, `POST /tasks/{id}/submissions`, `POST /submissions/{id}/review`, `POST /projects/{id}/activities`, `POST /schedule-changes`, `POST /schedule-changes/{id}/decision`, `GET /calendar/events`, `GET /datatable/...`, masing-masing dengan validasi pokok.
- Contoh Yajra (`DataTables::eloquent`, eager loading, `authorize('viewAny')`, scope sebelum DataTables, tanpa `rawColumns` HTML pengguna) dan JS (`datatables.net-bs5`, `serverSide: true`). Contoh Kanban SortableJS dengan PATCH + CSRF, kembalikan kartu bila API menolak; tunggu respons sebelum menyatakan berhasil; transaksi + `task_activities`.
- Debugbar di kedua app (--dev), uji daftar project, task, Management Overview; tanpa N+1; Yajra benar server-side; production `composer install --no-dev`; hasil uji dengan screenshot, jumlah query utama, catatan perbaikan.

### 10 Seeder Awal
- SQL sudah berisi role, permission, role_permissions, menu; jangan diulang dengan INSERT.
- Env: `INITIAL_ADMIN_EMAIL`, `INITIAL_ADMIN_PASSWORD` (>= 12), `DEMO_SEED_PASSWORD` (>= 12, lokal); jangan commit password; `Hash::make`; seeder tidak menimpa password lama.
- `InitialWorkspaceSeeder` (kode lengkap di dokumen): Super Admin + `user_roles` + `user_profiles`; Company A `COMP-A` + `company_members`; Client A `CLIENT-A`; divisi Development, Creative, Digital Marketing; jabatan Project Manager, Web Developer, UI/UX Designer, Graphic Designer, Video Editor, Digital Marketer; template project Website Development (one_time: Analisis Kebutuhan, Desain UI/UX, Frontend, Backend, Testing & Deployment), Produksi Konten (recurring: Riset Ide, Content Brief, Desain / Editing, Review, Publikasi), Digital Marketing Campaign (ongoing: Riset Audiens, Strategi Campaign, Materi Iklan, Monitoring, Laporan); template task Desain Feed Instagram, Video Pendek, Implementasi Halaman Web dengan masing-masing 4 subtask. Idempoten.
- `DemoWorkspaceSeeder` (local saja): demo.manager dan demo.member `@example.test`, role, company, profil, division_members Development + Web Developer, project `DEMO-PROJECT-001` Website Company Profile (Demo) one_time active, baseline/due +14 hari, project_divisions, project_members + project_member_divisions, task Buat Homepage (backlog, development, 480 menit, +7 hari) dengan dua assignee dan tiga subtask (Buat layout, Responsive mobile, QA browser).
- `DatabaseSeeder` memanggil Initial lalu Demo bila local; `php artisan db:seed` dari admin-app setelah impor SQL.

### 11 Tahapan & Pengujian
- Lima tahap: Fondasi, Project, Kanban & Task, Operasional, Management & QA (dipetakan ke LV-01..LV-05).
- 17 butir UAT: autentikasi, role & menu, Company/Client, keanggotaan unik, multi assignee, template + preview, Kanban (rollback saat API gagal), checklist, review, deadline/baseline, activity, kalender terpisah, management (anggota unik vs penugasan), Yajra server-side, Debugbar dev saja, responsive, performance tanpa N+1.
- 8 skenario wajib direkam: Campaign Sekolah dari template dengan 3 divisi; Landing Page dua orang; checklist + link + revisi + setuju; rapat + pengajuan deadline disetujui + target awal tetap; recurring periode baru tanpa menutup induk; Tim 390 dan 1366 px; Member memaksa URL Admin/Management (403/redirect); DataTables 500+ baris server-side.
- Referensi resmi: laravel.com/docs/13.x, yajrabox.com/docs/laravel-datatables/13.0, laraveldebugbar.com/installation, getbootstrap.com/docs/5.3, sortablejs.github.io/Sortable, fullcalendar.io/docs.
- Hasil akhir: source dua app, migrasi/schema terkendali, seeder, bukti uji semua role, video/gambar alur, catatan instalasi singkat.

## 2. Isi SQL

### 31 tabel (semua InnoDB utf8mb4_unicode_ci, FK ke `users.id` BIGINT UNSIGNED)
| Kelompok | Tabel | Catatan kolom penting |
|---|---|---|
| User & RBAC | user_profiles, roles, permissions, role_permissions, user_roles, menu_items | profil unik per user; menu `parent_id`, `route_name`, `permission_id` (SET NULL), `icon`, `sort_order` |
| Company & Client | companies, clients, company_members | companies/clients `code` unik + `deleted_at` |
| Organisasi | divisions, division_members, positions | divisi unik per company + `lead_user_id`; anggota divisi + `position_id` |
| Template | project_templates, project_template_tasks, task_templates, task_template_subtasks | template project `project_type`; task template `parent_id`, `relative_due_days`, `estimated_minutes` |
| Project | projects, project_divisions, project_members, project_member_divisions, project_periods | projects: `pic_user_id`, `baseline_due_at`, `due_at`, `status` (planned/active/on_hold/completed/cancelled), `recurrence_unit/interval`, `priority`, `deleted_at`; periode unik `period_code` per project |
| Task | tasks, task_assignees, task_submissions, task_attachments, task_activities, task_time_entries | tasks: `parent_id`, `project_period_id`, `status`, `baseline_due_at`, `due_at`, `completed_at/by`, `platform`, `scheduled_publish_at`, `published_at`, `publication_status`, `publication_url`, `deleted_at`; submissions `status` pending + `review_note`; attachments berkas atau `external_url` |
| Aktivitas | project_activities, project_activity_participants, schedule_change_requests | activity `activity_type`, `status`, `scheduled_at`, `occurred_at`, `duration_minutes`, `impact_type`; permintaan `previous_due_at`, `proposed_due_at`, `reason`, `status` pending, `applied_at` |
| Pengaturan | system_settings | `setting_key` unik |

Status dan jenis disimpan VARCHAR tanpa CHECK, jadi semua daftar kode ditegakkan aplikasi. SQL tidak membuat `users`, akun, atau password, dan tidak memuat DROP/TRUNCATE.

### 3 role
`super_admin` (semua 39 permission lewat CROSS JOIN, dicadangkan `Gate::before`), `manager` (27), `member` (11: dashboard.view, projects.view, tasks.view, tasks.create, tasks.update, tasks.move_status, tasks.submit, calendar.view, activities.view, activities.create, templates.view).

### 39 permission (module: slug)
dashboard: view | projects: view, create, update, delete, manage_team, approve_schedule | tasks: view, create, update, delete, assign, move_status, submit, review, approve_schedule | calendar: view | activities: view, create, update, delete | templates: view, manage | management: view, export | companies: view, manage | clients: view, manage | divisions: view, manage | positions: view, manage | members: view, manage | settings: roles.view, roles.manage, menus.manage, system.manage.
Manager tidak punya: projects.delete, tasks.delete, activities.delete, companies/clients/divisions/positions/members.manage, dan keempat settings.*.

### 9 menu induk + 23 submenu (route_name, permission)
1. Dashboard: Ringkasan Sistem `dashboard.index` (dashboard.view)
2. Projects: Daftar Project `projects.index` (projects.view)
3. Tasks: Semua Task `tasks.index`, Task Saya `tasks.mine` (tasks.view), Menunggu Review `tasks.review` (tasks.review)
4. Calendar: Kalender Pekerjaan & Aktivitas `calendar.index` (calendar.view)
5. Activities: Daftar Aktivitas `activities.index`, Rapat & Kunjungan `activities.meetings`, Hambatan & Perubahan `activities.impacts` (activities.view)
6. Templates: Template Project `templates.projects.index`, Template Task `templates.tasks.index` (templates.view)
7. Management: Management Overview `management.index`, Monitoring Project `management.projects`, Monitoring Divisi & Anggota `management.teams`, Laporan Operasional `management.reports` (management.view)
8. Master Data: Company `companies.index`, Client `clients.index`, Divisi `divisions.index`, Jabatan `positions.index`, Anggota Tim `members.index` (masing-masing *.view)
9. System Settings: Roles & Permissions `settings.roles.index` (settings.roles.view), Menu Management `settings.menus.index` (settings.menus.manage), Pengaturan Sistem `settings.general` (settings.system.manage)

Akibat langsung: Member melihat Dashboard, Projects, Tasks (Semua Task dan Task Saya, tanpa Menunggu Review), Calendar, Activities, Templates; tidak melihat Management, Master Data, System Settings. 23 nama route ini wajib terdaftar di admin-app.

## 3. Versi dan cara pasang dari sumber resmi (dibaca 11 Nov 2026)
| Komponen | Temuan | Sumber |
|---|---|---|
| Laravel 13 | PHP 8.3-8.5; rilis 17 Mar 2026; bug fix sampai Q3 2027, keamanan sampai 17 Mar 2028; laravel/framework v13.42.0 (10 Nov 2026), skeleton laravel/laravel v13.11.0 (require php ^8.3); `composer create-project laravel/laravel` tetap sah, dokumen resmi kini mencontohkan `laravel new`; MySQL lewat `DB_*` di `.env` lalu `php artisan migrate` | laravel.com/docs/13.x/releases, laravel.com/docs/13.x/installation, repo.packagist.org/p2/laravel/framework.json |
| Yajra DataTables 13 | PHP 8.3+, Laravel 13.x, DataTables.js 1.x atau 2.x; `composer require yajra/laravel-datatables:"^13.0"` (paket lengkap: oracle + HTML builder + buttons) atau inti `yajra/laravel-datatables-oracle:"^13.0"`; opsional `php artisan vendor:publish --tag=datatables`; v13.0.0 (25 Mar 2026) | yajrabox.com/docs/laravel-datatables/13.0/installation, packagist |
| Debugbar | `composer require fruitcake/laravel-debugbar --dev`; aktif bila `APP_DEBUG=true`, `DEBUGBAR_ENABLED` memaksa true/false; peringatan resmi: jangan dipakai di situs publik karena membocorkan data request; v4.4.5 (28 Okt 2026), illuminate ^11/^12/^13 | laraveldebugbar.com/installation, packagist |
| Bootstrap | 5.3.8 (npm), `@popperjs/core` 2.11.8 | npm registry 11 Nov 2026 |
| DataTables JS | **latest npm sudah 3.1.5** (DataTables 3 rilis 24 Jul 2026), sedangkan dokumen dan Yajra menyebut DataTables 2: kunci `datatables.net@^2.3.8` + `datatables.net-bs5@^2.3.8`; 2.3.x butuh `jquery >= 1.7` | npm registry, datatables.net/blog/2026 |
| jQuery | latest npm 4.0.0; **Keputusan**: kunci `jquery@^3.7.1` (3.7.1) karena DataTables 2 dan contoh dokumen ditulis untuk jQuery 3; uji jQuery 4 bukan tujuan proyek | npm registry |
| SortableJS | 1.15.7 (Feb 2026) | npm registry |
| FullCalendar | **jebakan**: `npm install @fullcalendar/core` kini memasang 7.1.4, yang hanya berisi tipe TypeScript (kalender vanilla pindah ke paket `fullcalendar`), sedangkan `@fullcalendar/daygrid` dan `interaction` terbaru 6.1.22 (dist-tag `legacy`); perintah dokumen apa adanya menghasilkan campuran v7 + v6 yang rusak. **Keputusan**: pakai nama paket persis dokumen dengan versi dikunci `@6.1.22` (core, daygrid, interaction, ditambah timegrid/list 6.1.22 bila perlu) | README `@fullcalendar/core@7.1.4` (npm pack), `npm view @fullcalendar/core dist-tags` = legacy 6.1.22, latest 7.1.4 |
| Vite | 8.3.8; laravel-vite-plugin 3.2.1 (ikut skeleton Laravel) | npm registry |
| Ikon | nama di `menu_items.icon` adalah Lucide; `lucide-static` 1.72.0 lisensi ISC (SVG tanpa framework) | npm registry |

## 4. Alat lokal (dicek 11 Nov 2026)
PHP 8.4.16 NTS dari Laravel Herd (ekstensi pdo_mysql, mbstring, openssl, intl, gd, zip, fileinfo, bcmath, sodium tersedia), Composer 2.9.5, Node 24.11.1 + npm 11.6.2, Docker 29.2.1 dengan image `mysql:8.4` (MySQL Community 8.4.13, memenuhi 8.0+) dan `php:8.4-fpm-alpine`, Git 2.52. Tidak ada yang perlu dibeli atau dipasang dengan akun.
**Keputusan**: MySQL lewat Docker (`mysql:8.4`, port lokal khusus, data di volume bernama, tanpa kata sandi di git); impor SQL satu kali seperti dokumen, lewat phpMyAdmin container resmi (`phpmyadmin` image) supaya langkah "import melalui phpMyAdmin" juga terbukti; dua app dijalankan `php artisan serve` di host berbeda port (admin :8101, team :8102) dengan cookie sesi berbeda.

## 5. Skill desain yang dipakai (isi SKILL.md dibaca 11 Nov 2026)
| Skill | Isi yang relevan | Dipakai untuk |
|---|---|---|
| impeccable 4.8.0 | Mode Operate untuk app/admin ("scanability, konsistensi, ekspektasi native mengalahkan ekspresi"); prinsip "the brief wins" (palet/font yang sudah dikunci brief dihormati); craft-floor; perintah `audit`, `polish`, `harden`, `onboard`; setiap komponen punya keadaan default/hover/focus/active/disabled/loading/error | **Utama**: dokumen sudah mengunci Bootstrap 5.3, Inter/system sans, putih/off-white + biru aksi, ukuran per elemen; impeccable mengerjakan dalam batas itu lalu `audit` + `polish` per tahap |
| design-taste-frontend (references/app-screens.md) | Studi 23 layar produk: warna di data bukan chrome, KPI berupa angka tanpa ikon berlingkar, permukaan datar dengan garis 1 px, tabel tanpa zebra dan angka rata kanan, keadaan data jujur (nol tetap nol, kosong tetap berbingkai), layar pertama memuat semua blok yang diminta | Daftar periksa Management Overview, Kanban, daftar Yajra; cocok dengan prototype 7.1/7.2 |
| emil-design-eng | Detail interaksi: transisi properti spesifik, `:active` tombol, asal transformasi, drag yang terasa benar; format tinjauan tabel Sebelum/Sesudah | Kanban SortableJS (umpan balik saat seret, kembali saat API menolak), dropdown status, notifikasi singkat |
| design:design-system | Token warna/huruf/spasi/radius/bayangan/gerak, varian dan keadaan komponen, dokumentasi | Menulis `docs/DESIGN-SYSTEM.md` sebagai variabel Bootstrap (`$primary`, radius 12-16, tinggi kontrol 44/40) yang sama di kedua app |
| frontend-design | Identitas visual khas untuk halaman bermerek; memperingatkan default generatif | **Tidak dipakai sebagai arah**: dokumen sudah menetapkan gaya produk kerja yang familier; dipakai hanya untuk memeriksa salinan dan label agar tidak templat |

Catatan: aturan anti-slop Nadi (misalnya larangan Inter) **tidak** berlaku di proyek ini; dokumen klien yang menang.

## 6. Hal yang tidak dijawab dokumen dan keputusan awal
- **Logika identik di dua app**: dokumen meminta logika status/progres/izin/deadline identik dan tes yang sama di kedua app, dengan struktur `app/Services` per app. **Keputusan**: Models, Policies, Services, Requests yang dipakai bersama ditulis sekali di admin-app lalu disalin ke team-app oleh skrip `docs/sinkron-domain.php`; tes `DomainSamaTest` di kedua app gagal bila isi salinan berbeda, sehingga struktur repo persis dokumen dan logika tidak bisa melenceng.
- **Tabel users sebelum impor**: FK SQL mengarah ke `users`, jadi `php artisan migrate` (admin-app) wajib mendahului impor (sesuai urutan 2.3).
- **Menu Management**: hanya urutan, ikon, nama, aktif, permission; `route_name` tidak bisa diisi bebas (dipilih dari daftar route terdaftar), sesuai larangan editor menu yang mengubah route.
- **Activity type, impact_type, status activity, publication_status, priority**: tidak ada daftar kode di SQL. **Keputusan**: activity_type `meeting, visit, revision, blocker, other`; impact_type `none, minor, delay, blocked`; status activity `planned, done, cancelled`; publication_status `draft, scheduled, published`; priority `low, normal, high, urgent`; label UI Indonesia; semua divalidasi di FormRequest bersama.
- **Kondisi monitoring (on-track/at risk/blocked/delayed)**: dihitung di ManagementMetricsService dari due_at vs sekarang, progres, dan activity berdampak blocked yang belum selesai; aturan persisnya ditulis dan diuji di LV-05.
- **Audit Super Admin "semua aksi dicatat"**: dicatat di `task_activities` untuk task; untuk master data tidak ada tabel audit di SQL dan dokumen melarang mengubah schema selain lewat migrasi admin-app, jadi tabel `audit_logs` ditambahkan lewat migrasi admin-app (13 Nov 2026) dan diisi middleware `CatatAksi` di kedua app; dilihat di Pengaturan Sistem, Log aktivitas (Super Admin).
