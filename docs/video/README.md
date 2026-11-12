# Video demo UAT

Rekaman sungguhan Admin App (:8101) dan Team App (:8102) yang berjalan di instalasi bersih (dipasang mengikuti README dari nol, `UatSkenarioSeeder`, Debugbar mati). Bukan animasi: setiap gambar adalah layar aplikasi yang sedang dipakai.

Hasil: `C:/Users/Tristan/Downloads/project-pkl/proposal/ptm-demo.mp4` (4 menit 28 detik, 1920x1080, 30 fps, H.264 + AAC, -15,9 LUFS, puncak -6 dBFS). Berkas video tidak masuk git.

## Alur (satu cerita, berurutan)

| Segmen | Siapa | Isi | Skenario 11.3 |
|---|---|---|---|
| `s1-buat-project.json` | Radit (manager), Admin | Buat Campaign Pendaftaran Sekolah dari template, pratinjau, 3 divisi, anggota | 1 |
| `s2-kanban.json` | Radit, Admin | Seret Riset Audiens ke Dikerjakan, ke Selesai ditolak lalu kartu kembali; Materi Iklan: PIC Putri, pelaksana Ayu dan Putri, 2 checklist | 2 |
| `s3-putri-kirim.json` | Putri (member), Tim di HP | Task Saya, centang checklist, kirim tautan hasil | 3, 6 |
| `s4-radit-revisi.json` | Radit, Admin | Menunggu Review, revisi beralasan | 3 |
| `s5-putri-ulang.json` | Putri, Tim di HP | Tanda Perlu revisi, lengkapi checklist, kirim ulang | 3 |
| `s6-radit-terima.json` | Radit, Admin | Terima hasil, task selesai | 3 |
| `s7-rapat-deadline.json` | Radit, Admin | Rapat tambahan + peserta, ajukan geser deadline 14 hari, setujui; target awal tetap | 4 |
| `s8-periode-baru.json` | Radit, Admin | Buka periode Desember tanpa menutup project; kartu November tetap di periode November | 5 |
| `s9-tim-desktop-403.json` | Putri | Tim di laptop tanpa navigasi bawah; Management dan Roles di Admin ditolak 403 | 6, 7 |
| `s10-datatables-520.json` | Radit, Admin | Arsip 520 task: 25 baris per halaman, halaman 2, cari 512, semuanya dari server | 8 |

## Merekam ulang

1. Jalankan kedua app tanpa Debugbar, data dari `php artisan db:seed --class=UatSkenarioSeeder`.
2. Dari folder ini: `CURSORCAM_SECRET_PASSWORD=<DEMO_SEED_PASSWORD> npx -y cursorcam@1.1.0 run s1-buat-project.json --style gaya.json`, lalu segmen berikutnya berurutan. Sandi tidak ditulis di berkas langkah (`$secret:PASSWORD`).
3. Segmen mengubah data. Jangan memakai `cursorcam check` untuk segmen yang menulis data, karena check menjalankan aksinya sungguhan. Bila satu segmen gagal di tengah, pulihkan keadaan sebelum merekam ulang (cadangan baris yang diubah disimpan di `cadangan/`, tidak masuk git).
4. `s9` membuka halaman yang memang menjawab 403. cursorcam 1.1.0 menganggap status 4xx sebagai galat, jadi segmen ini direkam dengan salinan lokal cursorcam (lisensi Apache-2.0) yang menambah satu syarat di `dist/record/waits.js`: status di env `CURSORCAM_IZINKAN_STATUS` (di sini `403`) tidak dianggap galat. Aplikasi tidak diubah untuk video.
5. Selama perekaman ditemukan satu masalah UX yang diperbaiki di aplikasi: isian deadline usulan (datetime-local) sulit diisi, jadi form aktivitas mendapat tombol geser cepat +7/+14/+30 hari yang dihitung dari deadline sekarang (task terpilih, lalu project), dengan tes fitur.

## Menyunting

`python -I susun.py` (env `PTM_BGM` = skrip musik latar sintetis lokal; opsional `PTM_FONTS`, `PTM_VIDEO_HASIL`, `PTM_VIDEO_KERJA`). Skrip membaca waktu setiap langkah dari log cursorcam (`waktu.py`), memotong login berulang setelah segmen pertama, memperkecil rekaman 88% supaya caption punya pita sendiri di bawah dan tidak menutupi UI, membakar caption bahasa Indonesia (Plus Jakarta Sans, OFL), menambah kartu pembuka dan penutup, menyambung, lalu memberi musik latar sintetis buatan lokal (tanpa lisensi pihak ketiga) yang dinormalkan ke -16 LUFS. Pemeriksaan: ffprobe, ebur128, dan lembar kontak bingkai setiap 9 detik.
