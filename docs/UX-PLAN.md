# Rencana peningkatan kegunaan (UX/UI)

Dimulai 15 Nov 2026 setelah penilaian pembina PKL (14 Nov 2026): **UX 3/10, UI 4/10**. Pesan utamanya: petunjuk teknis adalah batas minimum, bukan patokan utama; selama tidak dilarang, kembangkan ke arah yang membuat pengguna nyaman. Ukuran yang dipakai di sini: aplikasi yang developer sendiri mau pakai setiap hari, dan reviewer yang baru membuka zip bisa langsung mencoba tanpa bertanya.

Batas yang tetap dijaga dari petunjuk teknis (karena memang dilarang atau diukur): angka tabel 6.1 (huruf, area klik 44/40 px, padding, radius), warna aksi biru dan arti warna status, editor menu tidak membuat atau mengubah route PHP, server tetap memeriksa izin di setiap permintaan, dua aplikasi dengan satu database, nama tabel dan kolom SQL tidak diubah.

## Masukan pembina dan asalnya

| No | Masukan | Ada di petunjuk teknis? | Rencana |
|---|---|---|---|
| 1a | Kode dibuat otomatis | Tidak (inisiatif) | U4 |
| 1b | Status data baru otomatis aktif | Tidak, tetapi sudah aktif; kolom Status daftar template kosong membuat terlihat tidak aktif | U4 |
| 2 | Tampilan kurang modern dan ribet | Ribet: 6.3 dan kriteria penutup | U2, U3, U8, U9 |
| 3 | Tombol kecil tambah client/company di dalam formulir | Tidak (inisiatif) | U5 |
| 4 | Dikirim tidak siap pakai, tanpa akun awal | Bagian 10 (seeder) ada, tetapi pemasangan butuh banyak langkah dan sandi kosong membuat seeder gagal | U1 |
| 5a | Template tidak bisa dicoba | Akibat no. 4 | U1, U9 |
| 5b | Dashboard butuh dua klik | Pilihan desain sendiri (semua induk menu dapat dilipat) | U2 |
| 6 | Urutan menu lewat seret-lepas, pindah kelompok satu kontrol | Tidak (contoh sistem pembina) | U6 |
| 7 | Hak akses berupa centang, tanpa memilih route | Tidak (contoh sistem pembina) | U7 |
| 8 | Sedikit bahasa teknis | Tidak (prinsip) | U8 |

## Rencana induk

Setiap butir punya kriteria terima yang dibuktikan dengan tes otomatis atau tangkapan layar. Butir baru yang ditemukan saat bekerja ditambahkan sebagai sub-rencana (U-x.y).

### U1 Siap pakai dalam satu perintah
- [x] `setup.ps1` (Windows) dan `setup.sh` (Linux/macOS) di akar: menyalakan MySQL Docker, memasang dependensi, membangun aset, lalu `php artisan ptm:setup` di kedua app.
- [x] `php artisan ptm:setup` di Admin App: membuat `.env` bila belum ada (APP_KEY, sambungan database bawaan Docker), menunggu database, migrasi, impor SQL modul bila tabel belum ada, isi data awal + akun demo setiap peran + data contoh agensi (lokal saja), mencetak tabel akun.
- [x] `php artisan ptm:setup` di Team App: `.env` + APP_KEY + cek sambungan database yang sama.
- [x] Sandi demo lokal bawaan yang terdokumentasi bila kosong; di production seeder menolak sandi demo.
- [x] Halaman masuk lokal menampilkan tombol "Coba sebagai" untuk akun demo (hanya `APP_ENV=local` dan `PTM_DEMO_LOGIN=true`).
- [x] README: bagian "Coba dalam 5 menit" di paling atas, tabel akun demo.
- Kriteria terima: dari folder hasil ekstrak zip ke database kosong, satu perintah menghasilkan dua app yang bisa langsung dimasuki 3 peran, dengan project, task, template berisi subtask, dan aktivitas.

### U2 Navigasi satu klik
- [x] Sidebar baru: judul kelompok kecil, menu dengan satu submenu menjadi tautan langsung (Dashboard satu klik), setiap submenu berikon, kelompok bisa dilipat dan ingat pilihannya, latar terang setinggi layar.
- [x] Tombol "+ Buat" di kepala halaman: project, task, aktivitas, anggota (sesuai izin).
- [x] Pintasan `/` untuk fokus ke pencarian.
- Kriteria terima: setiap halaman dari sidebar tercapai dalam satu klik; tes HTTP sidebar.

### U3 Tampilan modern dan konsisten
- [x] Token desain baru (permukaan, garis, bayangan, radius) tetap dalam angka 6.1.
- [x] Tautan tanpa garis bawah kecuali saat disorot; judul di tabel sebagai tautan tebal.
- [x] Pill status bertitik, tombol, isian, tabel, tab, kartu KPI, kartu Kanban dirapikan.
- [x] Notifikasi singkat (toast) untuk pesan berhasil, hilang sendiri; konfirmasi hanya aksi berisiko.
- [x] Keadaan kosong dengan ikon dan satu langkah berikutnya.
- Kriteria terima: audit UI 0 masalah (axe, area klik, angka 6.1) di semua halaman 390 dan 1366 px.

### U4 Kode otomatis dan status aktif bawaan
- [x] Kode company dan client boleh dikosongkan: dibuat otomatis dari nama, unik, terlihat sebagai pratinjau saat mengetik.
- [x] Semua formulir data baru: Aktif menyala bawaan, berupa sakelar.
- [x] Kolom Status daftar template terisi (Aktif/Nonaktif).

### U5 Tambah data pendukung tanpa pindah halaman
- [x] Tombol kecil "Company baru" dan "Client baru" di formulir project: jendela kecil, simpan, langsung terpilih.
- [ ] Divisi dan jabatan baru dari formulir anggota (sub-rencana U5.1, ditunda ke putaran berikut).
- [x] Hanya muncul bagi yang berizin (Company/Client: Super Admin); server tetap memeriksa.

### U6 Pengaturan menu seret-lepas
- [x] Kelompok dan menu diurutkan dengan seret-lepas (dan tombol naik/turun untuk keyboard), tanpa kolom angka.
- [x] Pindah kelompok lewat satu pilihan di baris menu.
- [x] Sembunyikan/tampilkan, ganti nama, ikon dipilih dari gambar.
- [x] Tambah kelompok baru dan hapus kelompok kosong.
- [x] Route dan kode izin tidak ditampilkan; izin ditulis dengan nama yang dipahami ("Lihat Project").

### U7 Hak akses berupa centang
- [x] Tabel per peran: baris = menu/modul, kolom Lihat, Tambah, Ubah, Hapus, dan aksi khusus dengan nama biasa.
- [x] Centang Tambah/Ubah/Hapus otomatis mencentang Lihat; hitungan izin per kelompok; cari menu.
- [ ] Centang semua per baris (U7.1).
- [x] Tanpa slug izin di layar.

### U8 Bahasa awam
- [x] Audit semua teks yang tampil: tanpa route, slug, permission, assignee, submit; tab dan judul berbahasa Indonesia.
- [ ] Pesan galat menjelaskan apa yang terjadi dan apa yang bisa dilakukan.

### U9 Alur kerja tanpa langkah berulang
- [x] Tambah task satu layar (project dipilih di formulir yang sama, tanpa langkah "Lanjut").
- [x] Filter Kanban langsung berlaku tanpa tombol Saring.
- [x] Nilai bawaan pintar: tanggal mulai hari ini, PIC = pengguna yang membuat bila berhak, tipe project mengikuti template.
- [x] Daftar template: tombol "Pakai template" langsung ke Buat project dengan template terpilih.
- [x] Detail task: aksi utama menonjol, bagian lanjutan dilipat.
- [x] (temuan I0) Setelah menyimpan aktivitas di Team App, pengguna kembali ke formulir kosong dan tidak melihat catatannya: arahkan ke daftar aktivitas dengan catatan baru di atas.
- [x] (temuan I0) Laravel Debugbar menutupi navigasi bawah HP di instalasi lokal: dimatikan bawaannya (`DEBUGBAR_ENABLED=false` di `.env.example`).

### U10 Team App setara
- [x] Tampilan, istilah, halaman masuk demo, dan notifikasi sama dengan Admin App; navigasi bawah HP tetap 4 tombol.

### U11 Bukti dan serah terima
- [ ] PHPUnit kedua app, e2e, uji asap semua menu 3 peran, audit UI, pemeriksa rilis, pasang bersih dari zip.
- [ ] Tangkapan layar baru, video diperbarui bila alur berubah, zip baru.

## Inisiatif sendiri (di luar masukan pembina)

Ditambahkan 15 Nov 2026 setelah Tristan menegur bahwa rencana di atas hanya menjawab masukan. Sumber ide: memakai sendiri alur harian tiap peran di tangkapan layar terakhir, lalu mencatat setiap langkah yang terasa berulang, menunggu, atau membingungkan. Urutan = dampak bagi pengguna harian.

### Ukuran dulu, supaya "lebih mudah" bisa dibuktikan
- [x] **I0 Ukur klik dan waktu.** Skrip Playwright untuk 8 alur inti (buat project dari template, tambah task, mulai task, kirim hasil, terima review, ajukan geser deadline, catat aktivitas, tambah anggota) yang menghitung klik, ketikan isian, dan pindah halaman. Diukur sebelum dan sesudah; target setiap alur turun atau tetap, tidak ada yang naik. Hasil ditulis di tabel kemajuan.

### Tingkat A: dipakai setiap hari
- [x] **I1 Kotak "Perlu kamu kerjakan"** di Dashboard dan Beranda tim, untuk setiap peran: task saya yang jatuh tempo hari ini atau terlambat, hasil yang menunggu review saya, pengajuan geser deadline yang menunggu persetujuan saya, task yang dikembalikan untuk revisi. Satu klik ke tindakannya.
- [x] **I2 Aksi cepat di kartu Kanban dan baris daftar task**: tombol aksi utama sesuai status (Mulai, Kirim hasil, Terima/Revisi) langsung di kartu, tanpa membuka detail; tetap lewat aturan status di server.
- [ ] **I3 (ditunda ke putaran berikut; sebagai gantinya detail task dirapikan: revisi di atas, panel jarang dipakai dilipat) Detail task sebagai panel samping** dari Kanban dan daftar: buka, centang checklist, kirim hasil, tutup, posisi papan dan filter tidak hilang (6.3: jangan reset filter).
- [x] **I4 Tanggal yang bisa langsung dibaca**: "3 hari lagi", "terlambat 2 hari", "hari ini 17.00" di samping tanggal; pilihan cepat deadline (Hari ini, Besok, Jumat ini, +1 minggu) di setiap isian tanggal.
- [x] **I5 (sebagian: beban kerja di samping nama PIC dan pelaksana di Tambah task; kotak pencarian orang belum) Pemilih orang yang bisa dicari** (nama + inisial + divisi) untuk PIC, pelaksana, dan tim project, menggantikan daftar centang panjang dan multi-select bawaan peramban; menampilkan **beban kerja** (jumlah task aktif dan terlambat) supaya pembagian tugas adil.
- [x] **I6 Filter dan pencarian diingat** per halaman dan tercatat di alamat, sehingga kembali dari detail tidak mengulang penyaringan dan tautan bisa dibagikan.

### Tingkat B: membuat aplikasi terasa matang
- [x] **I7 Lonceng pemberitahuan di dalam aplikasi** (bukan push notification yang dikecualikan petunjuk): review baru, hasil dikembalikan, deadline disetujui/ditolak, ditambahkan ke task; dihitung dari data yang sudah ada, ditandai sudah dibaca.
- [x] **I8 Daftar langkah pertama** untuk Super Admin pada instalasi baru: lengkapi perusahaan, tambah anggota, cek template, buat project pertama; tercentang otomatis dari data dan bisa disembunyikan.
- [ ] **I9 (ditunda; pencarian global dengan pintasan / sudah ada) Palet perintah (Ctrl+K)**: cari project/task/anggota sekaligus menjalankan aksi ("Buat project", "Buka Task Saya", "Pengaturan menu"); pintasan `/` cari, `n` task baru, `?` daftar pintasan.
- [x] **I10 Log aktivitas berupa kalimat**: "Ayu memindahkan Desain Homepage ke Review" alih-alih metode dan alamat; disaring per orang dan per project.
- [x] **I11 Penyusun template seret-lepas**: urutan task template diseret, checklist ditambah langsung di baris, gandakan template.
- [x] **I12 Tambah anggota tanpa mengarang sandi**: sandi awal dibuat otomatis, ditampilkan sekali dengan tombol salin, anggota diminta menggantinya saat masuk pertama.
- [ ] **I13 (ditunda ke putaran berikut) Aksi massal di daftar task**: pilih beberapa task lalu ganti PIC, tambah pelaksana, atau pindah status yang diizinkan.
- [x] **I14 Formulir aman**: peringatan sebelum meninggalkan formulir yang belum disimpan; tombol simpan menampilkan proses dan tidak bisa terklik dua kali.

### Tingkat C: rapi di semua layar
- [x] **I15 Tabel jadi kartu di HP** untuk semua daftar Admin (6.1: ringkasan kartu di mobile), bukan tabel yang digeser.
- [x] **I16 Penjelasan istilah di tempat**: ikon tanda tanya kecil dengan satu kalimat untuk PIC, Review, periode, target awal, menggantikan paragraf petunjuk di atas halaman.
- [x] **I17 Avatar inisial atau foto** di kartu, daftar pelaksana, dan komentar, supaya orang dikenali sekilas.
- [x] **I18 Data contoh yang hidup**: aktivitas, pengajuan deadline, hasil yang menunggu review, dan task terlambat di data contoh, supaya setiap layar terisi saat dicoba (sekarang aktivitas contoh masih 0).

### Ide yang sengaja tidak diambil
- Mode gelap: 6.1 menetapkan latar putih/off-white.
- Obrolan langsung dan push notification: dikecualikan petunjuk teknis bagian 01 (diganti I7 di dalam aplikasi).
- Seret deadline di kalender langsung mengubah tanggal: deadline wajib lewat pengajuan dan persetujuan; yang mungkin adalah seret membuka formulir pengajuan (calon I19 bila waktu cukup).

## Hasil ukuran I0 (sebelum dan sesudah)

Langkah = klik + isian yang diketik/dipilih; halaman = halaman yang dimuat. Diukur dengan `docs/tools/ux-metrics.mjs` pada data yang sama (`docs/evidence/ux-metrics/before.json`, `after.json`).

| Alur | Langkah sebelum | Langkah sesudah | Halaman sebelum | Halaman sesudah |
|---|---|---|---|---|
| Buka Dashboard dari halaman lain | 2 | 1 | 1 | 1 |
| Buat project dari template | 6 | 6 | 2 | 2 |
| Tambah task dari halaman Tasks | 9 | 7 | 4 | 3 |
| Member memulai task (HP) | 3 | 2 | 3 | 2 |
| Member mengajukan geser deadline (HP) | 5 | 6 | 3 | 3 |
| Member mengirim hasil (HP) | 5 | 5 | 3 | 3 |
| Manager menerima hasil | 4 | 3 | 3 | 3 |
| Member mencatat rapat (HP) | 6 | 6 | 3 | 3 |
| Super Admin menambah anggota | 7 | 6 | 3 | 3 |
| Buat project untuk client yang belum ada | 10 | 5 | 5 | 1 |
| Pindahkan menu Kalender ke atas Task | 4 | 3 | 3 | 2 |
| Beri Manager izin hapus task | 4 | 3 | 3 | 2 |
| **Total** | **65** | **53** | **36** | **28** |

Geser deadline naik satu langkah dengan sengaja: panelnya dilipat di detail task karena jarang dipakai, supaya kirim hasil dan checklist tidak tertutup. Selain jumlah langkah, pengaturan menu tidak lagi mengetik angka urutan dan hak akses tidak lagi memilih kode izin.

## Catatan kemajuan

| Tanggal | Butir | Hasil | Bukti |
|---|---|---|---|
| 15 Nov 2026 | U1 | `ptm:setup` dari salinan tanpa `.env` ke database TiDB kosong: 9 akun, 4 project, 534 task, 3 template berisi 15 task, 12 checklist; dijalankan ulang aman; Team App tersambung | `DemoLoginTest` 4/4 kedua app |
| 15 Nov 2026 | I0 sebelum | 12 alur, total 65 langkah (klik + isian): Dashboard 2, project dari template 6, task dari halaman Tasks 9, mulai task 3, ajukan deadline 5, kirim hasil 5, terima review 4, catat rapat 6, tambah anggota 7, project untuk client baru 10, urutan menu 4, izin hapus task 4 | `docs/evidence/ux-metrics/before.json` |
| 15 Nov 2026 | Bug lama | Kolom Status dan tombol Ubah/Hapus kosong di semua daftar (Company, Client, Template, dan lainnya) karena DataTables 2 membaca atribut `data-columns` sebagai opsinya sendiri; atribut diganti `data-table-columns` | `TemplateListTest` (termasuk tes yang menolak atribut lama) |
| 15 Nov 2026 | U2, U4-U7, U9, I1 | Dashboard satu klik, kode otomatis, Client/Company baru dari formulir project, menu seret-lepas, hak akses centang, task satu layar, kotak Perlu kamu kerjakan | `SidebarTest`, `QuickAddTest`, `MenuSettingsTest`, `RoleMatrixTest`, `TaskQuickAddTest`, `TodoTest` |
| 15 Nov 2026 | I0 sementara | Dashboard 2 jadi 1, task dari halaman Tasks 9 jadi 7, project untuk client baru 10 jadi 5, urutan menu 4 jadi 3 tanpa mengetik angka | skrip `docs/tools/ux-metrics.mjs` |

