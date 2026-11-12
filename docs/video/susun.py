"""Menyusun video demo Project & Team Management dari rekaman cursorcam.
Potong login berulang, caption bahasa Indonesia dibakar, kartu pembuka/penutup, sambung, musik lokal, master -16 LUFS.
Pakai: python -I susun.py  (cwd bebas; kerja di folder skrip)."""
import json
import os
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from waktu import OUT, peta  # noqa: E402

KERJA = os.environ.get('PTM_VIDEO_KERJA') or os.path.join(os.path.dirname(os.path.abspath(__file__)), 'tmp-susun')
FONTS = os.environ.get('PTM_FONTS', r'C:\Users\Tristan\Downloads\project-pkl\proposal\fonts')
BGM = os.environ['PTM_BGM']  # skrip musik latar sintetis lokal: <skrip> <out.wav> <detik> <gaya>
HASIL = os.environ.get('PTM_VIDEO_HASIL', r'C:\Users\Tristan\Downloads\project-pkl\proposal\ptm-demo.mp4')
LATAR = '0xEFE9DD'
FPS = 30

# (take, rentang dipertahankan [(a, b)] dalam detik video asli, caption [(a, b, teks)] dalam detik video asli)
SEG = [
    ('s1-buat-project-20261112-064345-8f29', [(0.3, None)], [
        (0.5, 9.2, 'Radit, manager agensi, masuk ke aplikasi Admin.'),
        (9.5, 21.2, 'Project baru untuk SMA Harapan Bangsa, tipe berkelanjutan, dari template Digital Marketing Campaign.'),
        (21.5, 25.0, 'Pratinjau menunjukkan task yang akan dibuat sebelum project disimpan.'),
        (25.0, 31.5, 'Tiga divisi ikut: Development, Creative, dan Digital Marketing.'),
        (31.5, 38.7, 'Anggota tim dipilih dari karyawan divisi itu.'),
        (38.7, 47.4, 'Sekali simpan, project dan 5 task dari template langsung ada di Kanban.'),
    ]),
    ('s2-kanban-20261112-071853-19fc', [(10.25, None)], [
        (10.3, 14.7, 'Seret Riset Audiens ke Dikerjakan. Status tersimpan di server.'),
        (14.7, 21.7, 'Ke Selesai ditolak: hasil harus dikirim dan ditinjau dulu. Kartu kembali ke tempatnya.'),
        (21.7, 35.0, 'Materi Iklan dikerjakan dua orang: PIC Putri, pelaksana Ayu dan Putri.'),
        (35.0, 50.0, 'Checklist dibuat langsung di detail task.'),
    ]),
    ('s3-putri-kirim-20261112-072021-3c77', [(9.0, None)], [
        (9.1, 14.0, 'Putri membuka aplikasi Tim di HP: Task Saya.'),
        (14.0, 16.4, 'Centang checklist tanpa membuka halaman edit.'),
        (16.4, 29.0, 'Kirim tautan hasil. Task pindah ke Review.'),
    ]),
    ('s4-radit-revisi-20261112-072109-5a11', [(10.55, None)], [
        (10.6, 14.3, 'Radit meninjau hasil di Menunggu Review.'),
        (14.3, 25.0, 'Revisi wajib beralasan. Alasannya tercatat di riwayat review.'),
    ]),
    ('s5-putri-ulang-20261112-072158-7b13', [(9.05, None)], [
        (9.1, 13.7, 'Di beranda Putri muncul tanda Perlu revisi.'),
        (13.7, 30.3, 'Putri melengkapi checklist lalu mengirim hasil kedua.'),
    ]),
    ('s6-radit-terima-20261112-072248-72d7', [(10.3, None)], [
        (10.35, 17.3, 'Radit menerima hasil. Task otomatis selesai.'),
        (17.3, 23.5, 'Waktu selesai dan peninjau tercatat di detail task.'),
    ]),
    ('s7-rapat-deadline-20261112-070346-040a', [(10.3, None)], [
        (10.4, 22.4, 'Rapat tambahan dengan pemilik website dicatat beserta peserta yang hadir.'),
        (22.4, 31.3, 'Butuh waktu lebih: ajukan geser deadline 14 hari beserta alasannya.'),
        (31.3, 39.9, 'Pengajuan menunggu persetujuan manager.'),
        (39.9, 44.0, 'Disetujui. Deadline baru 26 Des, target awal 12 Des tetap tercatat.'),
    ]),
    ('s8-periode-baru-20261112-072356-2ba9', [(10.35, None)], [
        (10.4, 18.2, 'Project berkelanjutan: buka periode baru tanpa menutup project induk.'),
        (18.2, 28.3, 'Periode Desember dibuka dengan task baru dari template.'),
        (28.2, 39.7, 'Kartu bulan November tetap di periode November.'),
    ]),
    ('s9-tim-desktop-403-20261112-070912-c313', [(8.85, 16.3), (28.75, None)], [
        (8.9, 16.3, 'Di laptop, aplikasi Tim memakai menu samping tanpa navigasi bawah.'),
        (28.8, 33.1, 'Putri mencoba membuka Management di aplikasi Admin: ditolak, kode 403.'),
        (33.1, 38.9, 'Pengaturan role juga tertutup untuk member.'),
    ]),
    ('s10-datatables-520-20261112-071036-9868', [(10.5, None)], [
        (10.6, 15.1, 'Arsip 520 task. Tabel memuat 25 baris per halaman dari server.'),
        (15.1, 24.0, 'Pindah halaman meminta data berikutnya ke server.'),
        (24.0, 32.0, 'Pencarian juga disaring di server: 1 dari 520 baris.'),
    ]),
]

KARTU = [
    ('00-buka', 4.0, [('PlusJakartaSans-Bold.ttf', 76, 'Project & Team Management', 430),
                      ('PlusJakartaSans-Regular.ttf', 38, 'Admin App dan Team App, satu alur kerja agensi', 540),
                      ('PlusJakartaSans-Regular.ttf', 28, 'Rekaman aplikasi yang berjalan, data sintetis, 12 November 2026', 610)]),
    ('99-tutup', 4.5, [('PlusJakartaSans-Bold.ttf', 64, '8 skenario UAT lulus', 440),
                       ('PlusJakartaSans-Regular.ttf', 34, 'Template, Kanban, review, deadline, periode, akses role, DataTables', 535),
                       ('PlusJakartaSans-Regular.ttf', 28, 'Laravel 13 · MySQL 8.4 · Bootstrap 5.3 · Yajra DataTables · SortableJS · FullCalendar', 600)]),
]


def jalan(args):
    r = subprocess.run(args, cwd=KERJA, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if r.returncode != 0:
        sys.exit('GAGAL: ' + ' '.join(args[:6]) + '\n' + r.stderr[-3000:])
    return r


def srt_waktu(t):
    ms = int(round(t * 1000))
    return f'{ms // 3600000:02d}:{ms // 60000 % 60:02d}:{ms // 1000 % 60:02d},{ms % 1000:03d}'


def petakan(t, rentang):
    """Detik video asli -> detik keluaran setelah potong; None bila dibuang."""
    acc = 0.0
    for a, b in rentang:
        if a <= t <= b:
            return acc + t - a
        acc += b - a
    return None


def segmen(no, take, rentang, caption):
    dur = peta(take)[3]
    rentang = [(a, min(b if b is not None else dur, dur)) for a, b in rentang]
    total = sum(b - a for a, b in rentang)
    srt = []
    for i, (a, b, teks) in enumerate(caption, 1):
        x, y = petakan(a, rentang), petakan(min(b, rentang[-1][1]), rentang)
        if x is None or y is None:
            sys.exit(f'caption di luar rentang: {take} {a}-{b}')
        srt.append(f'{i}\n{srt_waktu(x)} --> {srt_waktu(y - 0.05)}\n{teks}\n')
    nama_srt = f'seg{no:02d}.srt'
    open(os.path.join(KERJA, nama_srt), 'w', encoding='utf-8', newline='\n').write('\n'.join(srt))
    sumber = os.path.join(OUT, take, 'video.mp4')
    bagian = ''.join(f'[0:v]trim={a:.3f}:{b:.3f},setpts=PTS-STARTPTS[p{i}];' for i, (a, b) in enumerate(rentang))
    gabung = ''.join(f'[p{i}]' for i in range(len(rentang))) + f'concat=n={len(rentang)}:v=1:a=0[g];' if len(rentang) > 1 else ''
    masuk = '[g]' if len(rentang) > 1 else '[p0]'
    # Rekaman diperkecil 88% dan dinaikkan, jadi caption punya pita sendiri di bawah dan tidak menutupi UI.
    gaya = ('FontName=Plus Jakarta Sans,FontSize=12,Bold=0,PrimaryColour=&H00332014,'
            'BorderStyle=1,Outline=0,Shadow=0,MarginV=15,MarginL=30,MarginR=30,Alignment=2')
    f = (bagian + gabung + f'{masuk}fps={FPS},scale=1690:-2,pad=1920:1080:115:10:color={LATAR},'
         f'subtitles={nama_srt}:fontsdir=fonts:force_style=\'{gaya}\','
         f'fade=t=in:st=0:d=0.35:color={LATAR},fade=t=out:st={total - 0.35:.3f}:d=0.35:color={LATAR},format=yuv420p[o]')
    keluar = f'seg{no:02d}.mp4'
    jalan(['ffmpeg', '-v', 'error', '-y', '-i', sumber, '-filter_complex', f, '-map', '[o]', '-c:v', 'libx264',
           '-preset', 'medium', '-crf', '20', '-r', str(FPS), '-video_track_timescale', '15360', keluar])
    return keluar, total


def kartu(nama, durasi, baris):
    teks = ','.join(f"drawtext=fontfile=fonts/{fnt}:text='{t.replace(':', chr(92) + ':')}':fontsize={uk}:fontcolor=0x142033:x=(w-text_w)/2:y={y}"
                    for fnt, uk, t, y in baris)
    keluar = f'{nama}.mp4'
    jalan(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', f'color=c={LATAR}:s=1920x1080:d={durasi}:r={FPS}',
           '-vf', teks + f',fade=t=in:st=0:d=0.4:color={LATAR},fade=t=out:st={durasi - 0.4}:d=0.4:color={LATAR},format=yuv420p',
           '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-r', str(FPS), '-video_track_timescale', '15360', keluar])
    return keluar, durasi


def main():
    os.makedirs(os.path.join(KERJA, 'fonts'), exist_ok=True)
    for f in os.listdir(FONTS):
        if f.endswith('.ttf'):
            shutil.copy(os.path.join(FONTS, f), os.path.join(KERJA, 'fonts', f))
    daftar = [kartu(*KARTU[0])]
    for no, (take, rentang, caption) in enumerate(SEG, 1):
        daftar.append(segmen(no, take, rentang, caption))
        print(f'seg{no:02d} {daftar[-1][1]:.1f} dtk', flush=True)
    daftar.append(kartu(*KARTU[1]))
    total = sum(d for _, d in daftar)
    open(os.path.join(KERJA, 'daftar.txt'), 'w', encoding='utf-8', newline='\n').write(''.join(f"file '{n}'\n" for n, _ in daftar))
    jalan(['ffmpeg', '-v', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', 'daftar.txt', '-c', 'copy', 'gambar.mp4'])
    jalan([sys.executable, '-I', BGM, 'bed.wav', f'{total + 1:.1f}', 'focus'])
    jalan(['ffmpeg', '-v', 'error', '-y', '-i', 'gambar.mp4', '-i', 'bed.wav', '-filter_complex',
           f'[1:a]atrim=0:{total:.3f},afade=t=in:st=0:d=1.5,afade=t=out:st={total - 3:.3f}:d=3,loudnorm=I=-16:TP=-1.5:LRA=11[a]',
           '-map', '0:v', '-map', '[a]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '160k', '-ar', '48000', '-movflags', '+faststart', '-shortest', HASIL])
    print(f'total {total:.1f} dtk -> {HASIL}')


if __name__ == '__main__':
    main()
