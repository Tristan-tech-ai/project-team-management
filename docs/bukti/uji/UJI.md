# Hasil uji LV-07

Dijalankan 12 Nov 2026 terhadap **instalasi bersih** yang dipasang mengikuti README di folder terpisah (database baru, seeder dokumen + `UatSkenarioSeeder`), dengan Debugbar mati. Semua berkas bukti ada di folder ini.

| Bukti | Isi | Hasil |
|---|---|---|
| `phpunit-admin-app.xml` | `php artisan test` admin-app (25 tes domain + 16 tes fitur) | 41 lulus, 0 gagal |
| `phpunit-team-app.xml` | `php artisan test` team-app (25 tes domain identik + 6 tes fitur) | 31 lulus, 0 gagal |
| `e2e-alur.txt`, `e2e/*.png` | Uji peramban sungguhan, dua app, HP 390 dan desktop 1366 | 17 lulus, 0 gagal |
| `asap-admin.txt` | Super Admin, Manager, Member membuka setiap menu Admin | 53 halaman, 0 gagal |
| `asap-tim.txt` | Ketiga role membuka setiap menu Tim + board, project, task | 29 halaman, 0 gagal |
| `../ui/AUDIT.md` | axe dan tangkapan 390/1366 | 0 pelanggaran serius |
| `../performa.md`, `../debugbar.txt` | Query per halaman, N+1, Debugbar | lihat berkas |
| `../../video/ptm-demo.mp4` | Video rekaman 8 skenario 11.3 dalam satu alur (Radit manager, Putri member) | 4 menit 28 detik |

## Checklist penerimaan 11.2

| Area | Kriteria | Status | Bukti |
|---|---|---|---|
| Autentikasi | Tanpa login tidak bisa membuka Admin/Tim; URL langsung ditolak Policy | Lulus | `test_tamu_diarahkan_ke_masuk`, `test_tamu_diarahkan_dan_login_member_berhasil`, e2e langkah Member 403 |
| Role & menu | Super Admin 9 menu, Member hanya menu berizin; menu tidak memberi akses backend | Lulus | `test_menu_dinamis_sesuai_izin` (9/23, 8/20, 6/10), `test_menu_nonaktif_tersembunyi_tetapi_route_tetap_dilindungi_policy`, `asap-admin.txt` |
| Company / Client | Master terpisah, tanpa Client PIC | Lulus | `test_crud_company_oleh_super_admin` (SoftDeletes), form Client tanpa PIC |
| Keanggotaan | Satu orang di beberapa divisi, anggota unik tidak ganda | Lulus | `test_anggota_unik_tidak_ganda_walau_di_beberapa_divisi`, `test_tambah_anggota_dengan_role_company_divisi_jabatan_lalu_bisa_masuk` |
| Multi assignee | Task >= 2 anggota; PIC task anggota project/assignee | Lulus | `test_task_dua_pelaksana_dan_pic_termasuk_assignee`, `test_pelaksana_harus_anggota_project`, e2e "task punya 2 pelaksana" |
| Template | Template menghasilkan seluruh task/subtask dan bisa dipratinjau | Lulus | `test_template_menghasilkan_semua_task_dan_subtask...`, `test_pratinjau_tidak_menulis_apa_pun`, `test_gagal_di_tengah_membatalkan_semuanya`, e2e pratinjau 5 task |
| Kanban | Empat kolom; drag desktop, dropdown HP, rollback saat API gagal | Lulus | e2e seret tersimpan, seret ke Selesai ditolak lalu kartu kembali, dropdown HP, `test_status_kanban_ditolak_mengembalikan_json_422_untuk_rollback` |
| Checklist | Subtask satu tingkat; centang tersimpan tanpa halaman edit | Lulus | `test_checklist_satu_tingkat_dan_progress`, e2e checklist HP bertahan setelah muat ulang |
| Review | Submission link/file, revisi beralasan, diterima menjadi done | Lulus | `test_alur_mulai_kirim_revisi_lalu_terima`, `test_kirim_hasil_wajib_tautan_atau_berkas`, e2e kirim, revisi, terima |
| Deadline | Overdue terdeteksi; baseline tetap; reschedule perlu persetujuan | Lulus | `test_hanya_pengajuan_disetujui_mengubah_deadline_dan_baseline_tetap`, `test_penolakan_wajib_alasan...`, `test_overdue_dinamis...` |
| Activity | Rapat/kunjungan dengan peserta aktual dan dampak | Lulus | e2e aktivitas berpeserta + pengajuan deadline; validasi peserta anggota project |
| Calendar | Deadline, publikasi, kegiatan, selesai aktual terpisah | Lulus | `test_mode_kalender_terpisah` (bukan updated_at), `test_kalender_hanya_project_dalam_scope`, e2e mode Jadwal dan Selesai |
| Management | KPI sesuai query; anggota unik vs penugasan dibedakan | Lulus | `test_overdue_dinamis_dan_kpi_membedakan_anggota_unik_dari_penugasan`, `test_progres_di_daftar_yajra_terhitung` |
| Yajra | Daftar Admin server-side: cari, sort, page, filter, tindakan sesuai izin | Lulus | `test_yajra_server_side_dengan_500_baris...`, e2e DataTables 520 baris |
| Debugbar | Dev saja; tidak muncul ketika APP_DEBUG=false | Lulus | `../debugbar.txt` (APP_DEBUG=false: 0 markup, `/_debugbar/open` 404), `require-dev` |
| Responsive | Bottom nav hanya HP, hilang di desktop tanpa ruang kosong | Lulus | `test_bottom_nav_hanya_hp_dan_sidebar_hanya_desktop`, e2e Tim 390 dan 1366 (padding bawah 28px), `../ui/audit-tim.json` |
| Performance | Tanpa N+1 utama; galat server ramah | Lulus | `test_tanpa_n_plus_1_daftar_project_dan_task`, `test_halaman_galat_ramah`, `../performa.md` |

## Skenario wajib 11.3

| # | Skenario | Hasil | Bukti |
|---|---|---|---|
| 1 | Manager membuat Campaign Sekolah dari template; tiga divisi dipilih; task otomatis muncul | Lulus: pratinjau 5 task, project dibuat dengan 5 task di Antrian dan 3 divisi | e2e langkah 1, `e2e/01-board-baru.png` |
| 2 | Task Landing Page dikerjakan dua orang; masing-masing melihat task sama di My Tasks | Lulus | `test_task_kolaboratif_terlihat_di_task_saya_kedua_pelaksana` (team-app), e2e dua pelaksana |
| 3 | Member checklist, kirim link, PIC minta revisi, lalu menyetujui | Lulus | e2e langkah 4 dan 5, `e2e/04-tim-task-review-390.png` |
| 4 | Rapat tambahan; pengajuan deadline disetujui; target awal tetap dapat ditinjau | Lulus | e2e langkah 6, `e2e/05-aktivitas-jadwal.png` |
| 5 | Project recurring memasuki periode baru tanpa menutup induk; kartu lama di periode sebelumnya | Lulus | e2e langkah 8 (Konten Sosial Media Kopi Senja), `e2e/06-periode-baru.png`, `test_recurring_membuka_periode_baru...` |
| 6 | Halaman Tim 390 dan 1366; bottom nav hanya di HP | Lulus | e2e langkah 4 dan 9, `e2e/03-tim-board-390.png`, `e2e/07-tim-1366.png` |
| 7 | Member memaksa URL Admin/Management: 403/redirect aman | Lulus | e2e langkah 10, `e2e/08-member-403.png`, `test_member_yang_memaksa_url_admin_mendapat_403` |
| 8 | DataTables 500+ data: pagination/filter di server | Lulus: total 520, tiap permintaan 25 baris, halaman 2 start=25, cari "512" disaring server menjadi 1 | e2e langkah 11, `e2e/09-datatables-520.png` |

## Temuan saat uji pasang dari nol (sudah diperbaiki)

1. `docs/alat/siapkan-db-uji.sh` mengandaikan database `ptm_test` sudah ada; kini membuatnya dan memberi hak sendiri.
2. `phpunit.xml` merujuk folder `tests/Unit` yang kosong sehingga tidak ikut git; suite kosong itu dibuang.
3. Uji asap Tim sempat memakai project yang bukan milik Member; 403 yang muncul adalah perilaku benar, URL uji diganti ke project yang diikuti ketiga akun.

## Temuan selama pengembangan dan pengujian (sudah diperbaiki)

1. Pembuatan project gagal karena kolom `is_active` ambigu saat memeriksa anggota company; ditangkap tes domain, kolom diberi nama tabel.
2. Progres di daftar project dan checklist di daftar task selalu 0% karena `select('tabel.*')` menimpa `withCount`; urutan diperbaiki dan dijaga tes.
3. Yajra mengembalikan galat query sebagai JSON 200 berkunci error sehingga tes bisa lulus palsu; tes kini menolak respons bergalat.
4. `$this->authorize()` tidak tersedia karena Controller dasar Laravel 13 kosong; trait AuthorizesRequests ditambahkan.
5. `@json` multibaris di atribut Blade gagal dikompilasi (halaman daftar 500); diganti `json_encode`.
6. `map('intval')` mengirim kunci sebagai basis (dashboard 500); diganti fungsi panah.
7. Atribut `data-pratinjau` dipakai ganda di form dan wadah pratinjau; dinamai ulang.
8. Pesan tolak Kanban ke Selesai dibuat menjelaskan jalan yang benar (kirim hasil untuk review).
9. Bukti Debugbar pertama memakai APP_ENV=production; diulang dengan APP_DEBUG=false saja.
10. Audit axe: kontras lencana hijau, tombol sekunder, label paginasi, tabel bergulir, dan FullCalendar diperbaiki (lihat `../ui/AUDIT.md`).
11. Saat merekam video, deadline usulan di form aktivitas sulit diisi dan mudah salah hari; ditambah tombol geser cepat +7/+14/+30 hari yang dihitung dari deadline sekarang (task terpilih, lalu project) dan baris "Deadline project sekarang", dijaga `test_form_aktivitas_memberi_dasar_geser_dari_deadline_sekarang`.

## Uji versi online (12 Nov 2026)

Versi online: Admin App https://ptm-admin.vercel.app dan Team App https://ptm-team.vercel.app (Vercel Container Runtime), database TiDB Cloud Starter (kompatibel MySQL). Kode dan skema sama dengan versi lokal; yang ditambahkan hanya `Dockerfile` dan `docker/` per app.

| Uji | Cara | Hasil |
|---|---|---|
| Migrasi + impor SQL modul ke TiDB | `php artisan migrate --force` lalu `docs/alat/impor-sql.php` | lulus, 61 pernyataan, 40 tabel |
| Tes Laravel ke database uji di TiDB (`ptm_test`) | `php artisan test` di kedua app | admin 41/41, tim 31/31 lulus |
| Semua menu 3 peran, data database online | `docs/alat/asap.mjs` ke admin-app lokal yang tersambung ke TiDB | 0 gagal |
| Alur kerja ujung ke ujung, data database online | `docs/alat/e2e-alur.mjs` ke admin-app + team-app lokal yang tersambung ke TiDB ([online-db-alur.txt](online-db-alur.txt)) | 15 lulus, 0 gagal |
| Pemeriksaan dari luar | `/up` dan `/masuk` kedua app, formulir dan cookie, halaman 404 | 200; formulir memakai https; cookie sesi `secure` dan `httponly`; sesi tercatat di database online; 404 tanpa jejak debug; Debugbar tidak terpasang |
| Uji langsung di alamat online dengan masuk akun (3 peran + alur kerja) | `asap.mjs` + `e2e-alur.mjs` dengan `E2E_ADMIN=https://ptm-admin.vercel.app` dan `E2E_TIM=https://ptm-team.vercel.app` | menunggu dijalankan pemilik akun demo |
