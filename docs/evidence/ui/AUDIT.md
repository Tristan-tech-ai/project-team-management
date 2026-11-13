# Audit UI

Diulang 13 Nov 2026 setelah penyeragaman nama ke bahasa Inggris, dengan alat yang kini juga mengukur angka bagian 06 Petunjuk Teknis. Arah desain dikunci bagian 06 (Bootstrap 5.3, Inter/system sans, putih/off-white, biru untuk aksi, merah terlambat, kuning review, hijau selesai). Skill yang dipakai sesuai `docs/RESEARCH.md`: impeccable mode Operate (kontras, keadaan, fokus, salinan yang menamai aksi), daftar periksa app-screens (warna di data, KPI berupa angka, permukaan datar, tabel tanpa zebra), emil-design-eng untuk interaksi Kanban.

## Cara

`docs/tools/ui-audit.mjs` masuk sebagai peran yang relevan, membuka setiap layar di 390 px (HP, sentuh) dan 1366 px, menyimpan tangkapan halaman penuh di folder ini, lalu memeriksa:

- **axe** WCAG 2.1 A/AA, dihitung pelanggaran serious dan critical;
- **gulir samping** halaman;
- **area klik** (tabel 6.1): setiap tombol, tautan, isian, checkbox bersama labelnya, judul lipatan, kepala tabel yang bisa diurutkan, paginasi, kontrol dan kejadian kalender, dan pegangan seret minimal 44 x 44 px di HP dan 40 x 40 px di desktop;
- **ukuran tabel 6.1**: body 16 px HP / 14-16 px desktop, line-height 1,45-1,6, H1 22-24 / 26-30 px, H2 18-20 / 20-24 px, padding halaman 12-16 / 24-32 px, padding kartu 16 / 18-24 px dengan radius 12-16 px, tinggi input dan tombol 44-48 / 40-44 px, bottom navigation 64-72 px, sidebar 232-260 px;
- **navigasi Tim**: bottom navigation hanya tampil di HP.

Server dijalankan dengan `DEBUGBAR_ENABLED=false` supaya tangkapan bersih. Rincian per halaman ada di `audit-admin.json`, `audit-team.json`, dan `audit-team-manager.json` (kolom `serious`, `smallTargets`, `styleIssues`).

## Hasil akhir

| App | Peran | Layar | Pemeriksaan (390 + 1366 px) | Masalah |
|---|---|---:|---:|---:|
| Admin | Super Admin | 37 | 74 | 0 |
| Tim | Manager (Review Task) | 2 | 4 | 0 |
| Tim | Member | 12 | 24 | 0 |

## Temuan dan perbaikan

| Temuan | Perbaikan |
|---|---|
| Area klik hanya dijaga pada tombol; tautan di tabel dan kartu 16-24 px, checkbox berlabel 23-24 px, judul lipatan 24 px, kepala tabel 43 px di HP, paginasi 36-40 px, tombol dan kejadian kalender 20-36 px, submenu sidebar 36 px, pegangan seret Kanban kecil | Satu token `--ptm-hit` (44 px HP, 40 px desktop) untuk semua area klik; checkbox tanpa label dibungkus label 44 px |
| Judul kartu dan kolom Kanban (H2) 16-16,8 px, di bawah 18-20 / 20-24 px | Judul H2 18,4 px HP dan 20 px desktop; judul kalender ikut |
| Kartu ringkasan status Tim padding 12 px dan kartu task desktop 16 px | Padding 16 px HP, 18 px desktop; ringkasan status dua kolom di HP sempit supaya label muat |
| Lencana hijau (Aktif, Selesai) 4,43:1, di bawah AA | `--ptm-done` #17713f (5,34:1); kuning review #8a5700 (5,56:1) |
| Tombol `btn-outline-secondary` bawaan Bootstrap kurang kontras | Warna teks #3d4757, garis #8b95a5, hover gelap |
| Label aria paginasi DataTables berbahasa Inggris sementara teks tampil Indonesia | `language.aria.paginate` Indonesia |
| Tabel bergulir tidak bisa dicapai keyboard | `.table-responsive` diberi tabindex, role region, dan label dari judul kartu |
| FullCalendar: tombol nonaktif transparan, ikon panah tanpa label, teks kejadian putih di latar lembut | Warna tombol dari token, ikon disembunyikan dari pembaca layar, teks kejadian mewarisi warna kontras |
| Tombol "+ Task" ganda di Task Saya desktop; tombol Keluar di sidebar Tim rata tengah | Disisakan satu; rata kiri |
| Debugbar terekam di tangkapan | Server tangkap memakai `DEBUGBAR_ENABLED=false` |

Catatan: tangkapan halaman penuh admin memperlihatkan sidebar berhenti di tinggi viewport karena sidebar `position: fixed`; di peramban sidebar selalu memenuhi layar.
