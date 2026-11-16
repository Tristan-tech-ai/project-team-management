# Video demo UAT

Rekaman sungguhan Admin App dan Team App yang berjalan di instalasi baru hasil `php artisan ptm:setup` (data awal, akun demo, data contoh agensi). Bukan animasi: setiap gambar aplikasi adalah layar yang sedang dipakai.

Hasil v3 (16 November 2026): `docs/video/ptm-demo.mp4`, direkam ulang setelah dua putaran peningkatan kegunaan, sehingga tampilan di video sama dengan aplikasi sekarang (panel samping detail task, palet Ctrl K, aksi massal, pengaturan menu seret-lepas, hak akses centang). Rekaman dirender tanpa bilah browser dan tanpa riak klik, lalu disusun sebagai film dengan gaya yang sama seperti v2: pembuka masalah kerja agensi, dua aplikasi, tiga peran, cara kerja, studi kasus Campaign Pendaftaran Sekolah 2027, 11 bab berlabel hari dan sudut pandang (Manager Radit, Member Putri di HP, Super Admin), dan momen sorot yang dibangun ulang dari teks asli rekaman. Musik: "Podcast Interview Background Music" oleh HitsLab (Pixabay, Pixabay Content License); efek suara CC0. Video v2 (12 November 2026) memakai tampilan lama dan digantikan.

## Alur (satu cerita, berurutan)

| Segmen | Siapa | Isi | Skenario 11.3 |
|---|---|---|---|
| `s1-create-project.json` | Radit (manager), Admin | Perlu kamu kerjakan di Dashboard, tombol Buat, project dari template dengan pratinjau, 3 divisi, anggota lewat Cari nama | 1 |
| `s2-kanban-panel.json` | Radit, Admin | Ctrl K ke project, seret Riset Audiens ke Dikerjakan, ke Selesai ditolak dan kartu kembali; Materi Iklan di panel samping: PIC Putri, pelaksana Ayu, 2 checklist, Esc | 2 |
| `s3-putri-submit.json` | Putri (member), Team di HP | Task Saya, Mulai dari kartu, Kirim hasil di panel layar penuh, centang checklist, kirim tautan | 3, 6 |
| `s4-radit-revision.json` | Radit, Admin | Review dari Perlu kamu kerjakan, revisi beralasan di panel | 3 |
| `s5-putri-resubmit.json` | Putri, Team di HP | Perlu revisi di beranda, catatan revisi di atas, minta geser deadline +3 hari dari deadline sekarang, kirim ulang | 3, 4 |
| `s6-radit-decide.json` | Radit, Admin | Setujui geser deadline dan terima hasil di panel yang sama; target awal tetap | 3, 4 |
| `s7-bulk.json` | Radit, Admin | Daftar Task: centang tiga task, Tambah pelaksana sekaligus | 2 |
| `s8-new-period.json` | Radit, Admin | Buka periode baru tanpa menutup project | 5 |
| `s9-admin-settings.json` | Super Admin | Pengaturan Menu seret-lepas, Role & Hak Akses berupa centang | 7 |
| `s10-team-desktop-403.json` | Putri | Team App di laptop dengan menu samping; Management di Admin ditolak 403 | 6, 7 |
| `s11-datatables-520.json` | Radit, Admin | Arsip 520 task: 25 baris per halaman, halaman 2, cari 512, semuanya dari server | 8 |

## Merekam ulang

1. Pasang database baru dengan `php artisan ptm:setup` (akun demo dan data contoh), jalankan kedua app tanpa Debugbar.
2. Dari folder ini: `CURSORCAM_SECRET_PASSWORD=<DEMO_SEED_PASSWORD> npx -y cursorcam@1.1.0 run s1-create-project.json --style style.json`, lalu segmen berikutnya berurutan. Sandi tidak ditulis di berkas langkah (`$secret:PASSWORD`); Super Admin di `s9` memakai sandi awal dari `INITIAL_ADMIN_PASSWORD`, yang pada pemasangan lokal sama dengan sandi demo.
3. Segmen mengubah data dan bergantung pada segmen sebelumnya (project `s1` dipakai sampai `s8`). Jangan memakai `cursorcam check` untuk segmen yang menulis data, karena check menjalankan aksinya sungguhan. Bila satu segmen gagal di tengah, pulihkan keadaan sebelum merekam ulang, atau rekam ke database baru.
4. `s10` membuka halaman yang memang menjawab 403. cursorcam 1.1.0 menganggap status 4xx sebagai galat, jadi segmen ini direkam dengan salinan lokal cursorcam (lisensi Apache-2.0) yang menambah satu syarat di `dist/record/waits.js`: status di env `CURSORCAM_ALLOW_STATUS` (di sini `403`) tidak dianggap galat. Aplikasi tidak diubah untuk video.
5. Selama perekaman v3 ditemukan dua masalah yang diperbaiki di aplikasi, dengan tes: pencarian global dan palet kini mencocokkan setiap kata terpisah (sebelumnya "pendaftaran 2027" tidak menemukan "Campaign Pendaftaran Sekolah 2027"), dan geser deadline di detail task kini menawarkan +3/+7/+14 hari dari deadline sekarang, bukan dari hari ini.

## Menyusun

Rekaman dirender ulang dengan gaya tanpa bilah browser, tanpa riak, dan tanpa pemangkasan diam (`cursorcam render <folder rekaman> --style <gaya film>`), lalu waktu tiap langkah dibaca dari log cursorcam, dipotong per rentang langkah (login berulang dibuang, jeda tunggu server dipercepat), dan disusun di HyperFrames 0.9.37 bersama momen sorot, lagu, dan efek; suara dinormalkan ke -16 LUFS. Skrip penyusunnya dipakai bersama video Nadi dan disimpan di luar repositori ini; berkas langkah rekaman (`s1` sampai `s11`) tetap di sini sehingga rekaman bisa diulang dari aplikasi.
