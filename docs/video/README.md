# Video demo UAT

Rekaman sungguhan Admin App (:8101) dan Team App (:8102) yang berjalan di instalasi bersih (dipasang mengikuti README dari nol, `UatScenarioSeeder`, Debugbar mati). Bukan animasi: setiap gambar adalah layar aplikasi yang sedang dipakai.

Catatan: video direkam 12 November 2026, sebelum perubahan tampilan 13 November 2026 (nama kode bahasa Inggris, area klik 44/40 px, timeline project gabungan, pencarian global). Alur dan aturannya sama; tampilan terbaru ada di tangkapan `../evidence/ui` dan `../evidence/tests/e2e`.

Hasil v2 (12 November 2026): `docs/video/ptm-demo.mp4` (3 menit 49 detik, 1920x1080, 30 fps, H.264 + AAC, -16 LUFS). Rekaman yang sama dirender ulang tanpa bilah browser dan tanpa riak klik, lalu disusun sebagai film: pembuka masalah kerja agensi, dua aplikasi, tiga peran, cara kerja, studi kasus Campaign Pendaftaran Sekolah, 10 bab berlabel hari dan sudut pandang (Manager Radit, Member Putri di HP), momen sorot yang dibangun ulang dari teks asli rekaman (task dari template, kartu Kanban yang ditolak masuk Selesai, revisi beralasan, cap DITERIMA, garis deadline dengan target awal tetap, 403, 520 task). Rekaman aplikasi 63% dari durasi. Musik: "Podcast Interview Background Music" oleh HitsLab (Pixabay, Pixabay Content License); efek suara CC0. Versi pertama (4 menit 28 detik, rekaman dengan caption polos) digantikan.

## Alur (satu cerita, berurutan)

| Segmen | Siapa | Isi | Skenario 11.3 |
|---|---|---|---|
| `s1-create-project.json` | Radit (manager), Admin | Buat Campaign Pendaftaran Sekolah dari template, pratinjau, 3 divisi, anggota | 1 |
| `s2-kanban.json` | Radit, Admin | Seret Riset Audiens ke Dikerjakan, ke Selesai ditolak lalu kartu kembali; Materi Iklan: PIC Putri, pelaksana Ayu dan Putri, 2 checklist | 2 |
| `s3-putri-submit.json` | Putri (member), Tim di HP | Task Saya, centang checklist, kirim tautan hasil | 3, 6 |
| `s4-radit-revision.json` | Radit, Admin | Menunggu Review, revisi beralasan | 3 |
| `s5-putri-resubmit.json` | Putri, Tim di HP | Tanda Perlu revisi, lengkapi checklist, kirim ulang | 3 |
| `s6-radit-approve.json` | Radit, Admin | Terima hasil, task selesai | 3 |
| `s7-meeting-deadline.json` | Radit, Admin | Rapat tambahan + peserta, ajukan geser deadline 14 hari, setujui; target awal tetap | 4 |
| `s8-new-period.json` | Radit, Admin | Buka periode Desember tanpa menutup project; kartu November tetap di periode November | 5 |
| `s9-team-desktop-403.json` | Putri | Tim di laptop tanpa navigasi bawah; Management dan Roles di Admin ditolak 403 | 6, 7 |
| `s10-datatables-520.json` | Radit, Admin | Arsip 520 task: 25 baris per halaman, halaman 2, cari 512, semuanya dari server | 8 |

## Merekam ulang

1. Jalankan kedua app tanpa Debugbar, data dari `php artisan db:seed --class=UatScenarioSeeder`.
2. Dari folder ini: `CURSORCAM_SECRET_PASSWORD=<DEMO_SEED_PASSWORD> npx -y cursorcam@1.1.0 run s1-create-project.json --style style.json`, lalu segmen berikutnya berurutan. Sandi tidak ditulis di berkas langkah (`$secret:PASSWORD`).
3. Segmen mengubah data. Jangan memakai `cursorcam check` untuk segmen yang menulis data, karena check menjalankan aksinya sungguhan. Bila satu segmen gagal di tengah, pulihkan keadaan sebelum merekam ulang (cadangan baris yang diubah disimpan di `backup/`, tidak masuk git).
4. `s9` membuka halaman yang memang menjawab 403. cursorcam 1.1.0 menganggap status 4xx sebagai galat, jadi segmen ini direkam dengan salinan lokal cursorcam (lisensi Apache-2.0) yang menambah satu syarat di `dist/record/waits.js`: status di env `CURSORCAM_ALLOW_STATUS` (di sini `403`) tidak dianggap galat. Aplikasi tidak diubah untuk video.
5. Selama perekaman ditemukan satu masalah UX yang diperbaiki di aplikasi: isian deadline usulan (datetime-local) sulit diisi, jadi form aktivitas mendapat tombol geser cepat +7/+14/+30 hari yang dihitung dari deadline sekarang (task terpilih, lalu project), dengan tes fitur.

## Menyunting

`python -I compose.py` (env `PTM_BGM` = skrip musik latar sintetis lokal; opsional `PTM_FONTS`, `PTM_VIDEO_RESULT`, `PTM_VIDEO_WORKDIR`). Skrip membaca waktu setiap langkah dari log cursorcam (`timing.py`), memotong login berulang setelah segmen pertama, memperkecil rekaman 88% supaya caption punya pita sendiri di bawah dan tidak menutupi UI, membakar caption bahasa Indonesia (Plus Jakarta Sans, OFL), menambah kartu pembuka dan penutup, menyambung, lalu memberi musik latar sintetis buatan lokal (tanpa lisensi pihak ketiga) yang dinormalkan ke -16 LUFS. Pemeriksaan: ffprobe, ebur128, dan lembar kontak bingkai setiap 9 detik.

## Menyusun v2

Rekaman dirender ulang dengan gaya tanpa bilah browser, tanpa riak, dan tanpa pemangkasan diam (`cursorcam render <folder rekaman> --style style.json`), lalu waktu tiap langkah dibaca dari log cursorcam, dipotong per rentang langkah (login berulang dibuang), dan disusun di HyperFrames bersama momen sorot, lagu, dan efek. Skrip penyusunnya dipakai bersama video Nadi dan disimpan di luar repositori ini; berkas langkah rekaman (`s1` sampai `s10`) tetap di sini sehingga rekaman bisa diulang dari aplikasi.
