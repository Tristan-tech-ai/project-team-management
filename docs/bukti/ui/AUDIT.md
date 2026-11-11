# Audit UI LV-06

Dikerjakan 12 Nov 2026. Arah desain dikunci Petunjuk Teknis bagian 06 (Bootstrap 5.3, Inter, putih/off-white, biru untuk aksi, merah terlambat, kuning review, hijau selesai). Skill yang dipakai sesuai `docs/RISET.md`: impeccable mode Operate (craft floor: kontras, keadaan, fokus, salinan yang menamai aksi), daftar periksa app-screens (warna di data, KPI berupa angka, permukaan datar, tabel tanpa zebra), emil-design-eng untuk interaksi Kanban.

## Cara

`docs/alat/audit-ui.mjs` masuk sebagai role yang relevan, membuka setiap layar utama di 390 px (HP, sentuh) dan 1366 px, menyimpan tangkapan halaman penuh di folder ini, menjalankan axe (WCAG 2.1 A/AA, dihitung hanya serious dan critical), mengukur gulir samping, dan untuk aplikasi Tim memastikan bottom navigation tampil hanya di HP. Server dijalankan dengan `--env=tangkap` (Debugbar dimatikan) supaya tangkapan bersih.

## Hasil akhir

| App | Layar | Pemeriksaan | Masalah |
|---|---:|---:|---:|
| Admin (Super Admin) | 28 | 56 | 0 |
| Tim (Member) | 11 | 22 | 0 |
| Tim (Manager, Review Task) | 1 | 2 | 0 |

Rincian per halaman: `audit-admin.json`, `audit-tim.json`, `audit-tim-manager.json`.

## Temuan dan perbaikan

| Temuan | Perbaikan |
|---|---|
| Lencana hijau (Aktif, Selesai) 4,43:1, di bawah AA | `--ptm-selesai` #17713f (5,34:1); kuning review #8a5700 (5,56:1) untuk ruang aman |
| Tombol `btn-outline-secondary` bawaan Bootstrap kurang kontras | Warna teks #3d4757, garis #8b95a5, hover gelap |
| Label aria paginasi DataTables berbahasa Inggris sementara teks tampil Indonesia (label-content-name-mismatch) | `language.aria.paginate` Indonesia |
| Tabel bergulir tidak bisa dicapai keyboard (scrollable-region-focusable) | `.table-responsive` diberi tabindex, role region, dan label dari judul kartu |
| FullCalendar: tombol nonaktif transparan, ikon panah berperan img tanpa label, sel hari berlabel beda dengan teks, teks kejadian putih di latar lembut | Warna tombol dari token, nonaktif pekat, ikon disembunyikan dari pembaca layar, label sel mengikuti teks tampil, teks kejadian mewarisi warna kontras |
| Tombol "+ Task" ganda di Task Saya versi desktop | Disisakan satu di kepala halaman |
| Tombol Keluar di sidebar Tim rata tengah | Rata kiri seperti butir menu lain |
| Debugbar terekam di tangkapan | Server tangkap memakai env terpisah dengan `DEBUGBAR_ENABLED=false` |

Catatan: tangkapan halaman penuh admin memperlihatkan sidebar berhenti di tinggi viewport karena sidebar `position: fixed`; di peramban sidebar selalu memenuhi layar.
