"""Builds the Project & Team Management demo video from cursorcam recordings.
Cuts repeated logins, burns in Indonesian captions, adds opening/closing cards, joins clips, local music, masters at -16 LUFS.
Usage: python -I compose.py  (any cwd; works in the script folder)."""
import json
import os
import shutil
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from timing import OUT, step_times  # noqa: E402

WORK = os.environ.get('PTM_VIDEO_WORKDIR') or os.path.join(os.path.dirname(os.path.abspath(__file__)), 'tmp-compose')
FONTS = os.environ.get('PTM_FONTS', r'C:\Users\Tristan\Downloads\project-pkl\proposal\fonts')
BGM = os.environ['PTM_BGM']  # local synthetic background music script: <script> <out.wav> <seconds> <style>
RESULT = os.environ.get('PTM_VIDEO_RESULT', r'C:\Users\Tristan\Downloads\project-pkl\proposal\ptm-demo.mp4')
BACKGROUND = '0xEFE9DD'
FPS = 30

# (take, kept ranges [(a, b)] in original video seconds, captions [(a, b, text)] in original video seconds)
SEG = [
    ('s1-create-project-20261112-064345-8f29', [(0.3, None)], [
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
    ('s3-putri-submit-20261112-072021-3c77', [(9.0, None)], [
        (9.1, 14.0, 'Putri membuka aplikasi Tim di HP: Task Saya.'),
        (14.0, 16.4, 'Centang checklist tanpa membuka halaman edit.'),
        (16.4, 29.0, 'Kirim tautan hasil. Task pindah ke Review.'),
    ]),
    ('s4-radit-revision-20261112-072109-5a11', [(10.55, None)], [
        (10.6, 14.3, 'Radit meninjau hasil di Menunggu Review.'),
        (14.3, 25.0, 'Revisi wajib beralasan. Alasannya tercatat di riwayat review.'),
    ]),
    ('s5-putri-resubmit-20261112-072158-7b13', [(9.05, None)], [
        (9.1, 13.7, 'Di beranda Putri muncul tanda Perlu revisi.'),
        (13.7, 30.3, 'Putri melengkapi checklist lalu mengirim hasil kedua.'),
    ]),
    ('s6-radit-approve-20261112-072248-72d7', [(10.3, None)], [
        (10.35, 17.3, 'Radit menerima hasil. Task otomatis selesai.'),
        (17.3, 23.5, 'Waktu selesai dan peninjau tercatat di detail task.'),
    ]),
    ('s7-meeting-deadline-20261112-070346-040a', [(10.3, None)], [
        (10.4, 22.4, 'Rapat tambahan dengan pemilik website dicatat beserta peserta yang hadir.'),
        (22.4, 31.3, 'Butuh waktu lebih: ajukan geser deadline 14 hari beserta alasannya.'),
        (31.3, 39.9, 'Pengajuan menunggu persetujuan manager.'),
        (39.9, 44.0, 'Disetujui. Deadline baru 26 Des, target awal 12 Des tetap tercatat.'),
    ]),
    ('s8-new-period-20261112-072356-2ba9', [(10.35, None)], [
        (10.4, 18.2, 'Project berkelanjutan: buka periode baru tanpa menutup project induk.'),
        (18.2, 28.3, 'Periode Desember dibuka dengan task baru dari template.'),
        (28.2, 39.7, 'Kartu bulan November tetap di periode November.'),
    ]),
    ('s9-team-desktop-403-20261112-070912-c313', [(8.85, 16.3), (28.75, None)], [
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

CARDS = [
    ('00-open', 4.0, [('PlusJakartaSans-Bold.ttf', 76, 'Project & Team Management', 430),
                      ('PlusJakartaSans-Regular.ttf', 38, 'Admin App dan Team App, satu alur kerja agensi', 540),
                      ('PlusJakartaSans-Regular.ttf', 28, 'Rekaman aplikasi yang berjalan, data sintetis, 12 November 2026', 610)]),
    ('99-close', 4.5, [('PlusJakartaSans-Bold.ttf', 64, '8 skenario UAT lulus', 440),
                       ('PlusJakartaSans-Regular.ttf', 34, 'Template, Kanban, review, deadline, periode, akses role, DataTables', 535),
                       ('PlusJakartaSans-Regular.ttf', 28, 'Laravel 13 · MySQL 8.4 · Bootstrap 5.3 · Yajra DataTables · SortableJS · FullCalendar', 600)]),
]


def run(args):
    r = subprocess.run(args, cwd=WORK, capture_output=True, text=True, encoding='utf-8', errors='replace')
    if r.returncode != 0:
        sys.exit('FAILED: ' + ' '.join(args[:6]) + '\n' + r.stderr[-3000:])
    return r


def srt_time(t):
    ms = int(round(t * 1000))
    return f'{ms // 3600000:02d}:{ms // 60000 % 60:02d}:{ms // 1000 % 60:02d},{ms % 1000:03d}'


def map_time(t, ranges):
    """Original video second -> output second after cutting; None when the moment is cut."""
    acc = 0.0
    for a, b in ranges:
        if a <= t <= b:
            return acc + t - a
        acc += b - a
    return None


def segment(no, take, ranges, captions):
    dur = step_times(take)[3]
    ranges = [(a, min(b if b is not None else dur, dur)) for a, b in ranges]
    total = sum(b - a for a, b in ranges)
    srt = []
    for i, (a, b, text) in enumerate(captions, 1):
        x, y = map_time(a, ranges), map_time(min(b, ranges[-1][1]), ranges)
        if x is None or y is None:
            sys.exit(f'caption outside kept ranges: {take} {a}-{b}')
        srt.append(f'{i}\n{srt_time(x)} --> {srt_time(y - 0.05)}\n{text}\n')
    srt_name = f'seg{no:02d}.srt'
    open(os.path.join(WORK, srt_name), 'w', encoding='utf-8', newline='\n').write('\n'.join(srt))
    source = os.path.join(OUT, take, 'video.mp4')
    parts = ''.join(f'[0:v]trim={a:.3f}:{b:.3f},setpts=PTS-STARTPTS[p{i}];' for i, (a, b) in enumerate(ranges))
    joined = ''.join(f'[p{i}]' for i in range(len(ranges))) + f'concat=n={len(ranges)}:v=1:a=0[g];' if len(ranges) > 1 else ''
    entry = '[g]' if len(ranges) > 1 else '[p0]'
    # Recordings are scaled to 88% and moved up, so captions get their own band below and never cover the UI.
    style = ('FontName=Plus Jakarta Sans,FontSize=12,Bold=0,PrimaryColour=&H00332014,'
            'BorderStyle=1,Outline=0,Shadow=0,MarginV=15,MarginL=30,MarginR=30,Alignment=2')
    f = (parts + joined + f'{entry}fps={FPS},scale=1690:-2,pad=1920:1080:115:10:color={BACKGROUND},'
         f'subtitles={srt_name}:fontsdir=fonts:force_style=\'{style}\','
         f'fade=t=in:st=0:d=0.35:color={BACKGROUND},fade=t=out:st={total - 0.35:.3f}:d=0.35:color={BACKGROUND},format=yuv420p[o]')
    output = f'seg{no:02d}.mp4'
    run(['ffmpeg', '-v', 'error', '-y', '-i', source, '-filter_complex', f, '-map', '[o]', '-c:v', 'libx264',
           '-preset', 'medium', '-crf', '20', '-r', str(FPS), '-video_track_timescale', '15360', output])
    return output, total


def card(name, duration, lines):
    text = ','.join(f"drawtext=fontfile=fonts/{fnt}:text='{t.replace(':', chr(92) + ':')}':fontsize={size}:fontcolor=0x142033:x=(w-text_w)/2:y={y}"
                    for fnt, size, t, y in lines)
    output = f'{name}.mp4'
    run(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', f'color=c={BACKGROUND}:s=1920x1080:d={duration}:r={FPS}',
           '-vf', text + f',fade=t=in:st=0:d=0.4:color={BACKGROUND},fade=t=out:st={duration - 0.4}:d=0.4:color={BACKGROUND},format=yuv420p',
           '-c:v', 'libx264', '-preset', 'medium', '-crf', '20', '-r', str(FPS), '-video_track_timescale', '15360', output])
    return output, duration


def main():
    os.makedirs(os.path.join(WORK, 'fonts'), exist_ok=True)
    for f in os.listdir(FONTS):
        if f.endswith('.ttf'):
            shutil.copy(os.path.join(FONTS, f), os.path.join(WORK, 'fonts', f))
    clips = [card(*CARDS[0])]
    for no, (take, ranges, captions) in enumerate(SEG, 1):
        clips.append(segment(no, take, ranges, captions))
        print(f'seg{no:02d} {clips[-1][1]:.1f} s', flush=True)
    clips.append(card(*CARDS[1]))
    total = sum(d for _, d in clips)
    open(os.path.join(WORK, 'clips.txt'), 'w', encoding='utf-8', newline='\n').write(''.join(f"file '{n}'\n" for n, _ in clips))
    run(['ffmpeg', '-v', 'error', '-y', '-f', 'concat', '-safe', '0', '-i', 'clips.txt', '-c', 'copy', 'picture.mp4'])
    run([sys.executable, '-I', BGM, 'bed.wav', f'{total + 1:.1f}', 'focus'])
    run(['ffmpeg', '-v', 'error', '-y', '-i', 'picture.mp4', '-i', 'bed.wav', '-filter_complex',
           f'[1:a]atrim=0:{total:.3f},afade=t=in:st=0:d=1.5,afade=t=out:st={total - 3:.3f}:d=3,loudnorm=I=-16:TP=-1.5:LRA=11[a]',
           '-map', '0:v', '-map', '[a]', '-c:v', 'copy', '-c:a', 'aac', '-b:a', '160k', '-ar', '48000', '-movflags', '+faststart', '-shortest', RESULT])
    print(f'total {total:.1f} s -> {RESULT}')


if __name__ == '__main__':
    main()
