"""Menyusun dokumen UAT (HTML siap cetak) dari docs/bukti/uji/UJI.md dan tangkapan bukti.
Pakai: python docs/alat/buat-uat.py  -> docs/UAT.html ; cetak: node docs/alat/cetak-pdf.mjs docs/UAT.html docs/UAT.pdf"""
import html
import os
import re

AKAR = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DOCS = os.path.join(AKAR, 'docs')
FONT = 'file:///C:/Users/Tristan/Downloads/project-pkl/proposal/fonts/'


def inline(t):
    t = html.escape(t)
    t = re.sub(r'`([^`]+)`', r'<code>\1</code>', t)
    return re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', t)


def md_ke_html(md):
    out, tabel = [], []

    def tutup():
        if tabel:
            kepala = [c.strip() for c in tabel[0].strip('|').split('|')]
            badan = [[c.strip() for c in r.strip('|').split('|')] for r in tabel[2:]]
            out.append('<table><thead><tr>' + ''.join(f'<th>{inline(c)}</th>' for c in kepala) + '</tr></thead><tbody>'
                       + ''.join('<tr>' + ''.join(f'<td class="{"lulus" if c == "Lulus" else ""}">{inline(c)}</td>' for c in r) + '</tr>' for r in badan)
                       + '</tbody></table>')
            tabel.clear()
    for baris in md.splitlines():
        if baris.startswith('|'):
            tabel.append(baris)
            continue
        tutup()
        if baris.startswith('# '):
            continue
        if baris.startswith('## '):
            out.append(f'<h2>{inline(baris[3:])}</h2>')
        elif re.match(r'^\d+\. ', baris):
            out.append(f'<p class="butir">{inline(baris)}</p>')
        elif baris.strip():
            out.append(f'<p>{inline(baris)}</p>')
    tutup()
    return '\n'.join(out)


LANGKAH = [
    ('Super Admin', 'Masuk ke Admin dengan admin@example.test. Buka setiap menu (9 menu, 23 submenu). Tambah Company, Client, Divisi, Jabatan, dan Anggota Tim. Ubah izin role Manager di Roles & Permissions, atur urutan menu, ubah zona waktu.'),
    ('Manager', 'Masuk ke Admin sebagai demo.manager. Buat project dari template dengan tiga divisi, cek pratinjau. Seret task di Kanban, tinjau hasil di Menunggu Review (revisi beralasan, lalu terima), setujui perubahan deadline, buka periode baru project berulang, ekspor laporan.'),
    ('Member', 'Masuk ke aplikasi Tim di HP sebagai demo.member. Buka Task Saya, ubah status lewat pilihan di kartu, centang checklist, kirim tautan hasil, catat aktivitas. Coba buka alamat /management dan /settings/roles di Admin: harus muncul halaman tidak berizin.'),
]

GAMBAR = [
    ('uji/e2e/01-board-baru.png', 'Skenario 1: project dari template, 5 task di Antrian, 3 divisi'),
    ('uji/e2e/02-rollback.png', 'Kanban: seret ke Selesai ditolak server, kartu kembali'),
    ('uji/e2e/03-tim-board-390.png', 'Aplikasi Tim 390 px: bottom navigation dan pilihan status'),
    ('uji/e2e/05-aktivitas-jadwal.png', 'Skenario 4: rapat tambahan dan deadline baru disetujui, target awal tetap'),
    ('uji/e2e/06-periode-baru.png', 'Skenario 5: periode baru project berulang'),
    ('uji/e2e/07-tim-1366.png', 'Skenario 6: aplikasi Tim 1366 px tanpa bottom navigation'),
    ('uji/e2e/08-member-403.png', 'Skenario 7: Member memaksa URL Admin, 403'),
    ('uji/e2e/09-datatables-520.png', 'Skenario 8: DataTables 520 baris, paging di server'),
]

isi = md_ke_html(open(os.path.join(DOCS, 'bukti', 'uji', 'UJI.md'), encoding='utf-8').read())
langkah = ''.join(f'<tr><td><strong>{p}</strong></td><td>{inline(t)}</td></tr>' for p, t in LANGKAH)
gambar = ''.join(f'<figure><img src="bukti/{g}" alt="{html.escape(k)}"><figcaption>{html.escape(k)}</figcaption></figure>'
                 for g, k in GAMBAR if os.path.exists(os.path.join(DOCS, 'bukti', g)))
doc = f'''<!doctype html><html lang="id"><head><meta charset="utf-8"><title>UAT Project & Team Management</title><style>
@font-face {{ font-family: PJS; src: url("{FONT}PlusJakartaSans-Regular.ttf"); font-weight: 400; }}
@font-face {{ font-family: PJS; src: url("{FONT}PlusJakartaSans-Bold.ttf"); font-weight: 700; }}
@page {{ size: A4; margin: 18mm 16mm 20mm; }}
body {{ font-family: PJS, sans-serif; color: #142033; font-size: 10.5pt; line-height: 1.45; }}
.sampul {{ height: 240mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }}
.sampul .kecil {{ color: #2557d6; font-weight: 700; letter-spacing: .04em; font-size: 10pt; }}
.sampul h1 {{ font-size: 28pt; margin: 6mm 0 4mm; line-height: 1.15; }}
.sampul p {{ color: #5a6678; font-size: 11pt; max-width: 150mm; }}
h2 {{ font-size: 14pt; margin: 8mm 0 3mm; page-break-after: avoid; }}
table {{ width: 100%; border-collapse: collapse; margin: 2mm 0 4mm; font-size: 9pt; }}
th, td {{ text-align: left; vertical-align: top; border-bottom: 1px solid #dfe5ee; padding: 1.8mm 2mm; }}
th {{ color: #5a6678; font-weight: 700; border-bottom: 1.5px solid #c9d2df; }}
tr {{ page-break-inside: avoid; }}
td.lulus {{ color: #17713f; font-weight: 700; }}
code {{ font-size: 8.5pt; background: #f2f5f9; padding: 0 1mm; border-radius: 1mm; word-break: break-word; }}
figure {{ margin: 0 0 6mm; page-break-inside: avoid; }}
figure img {{ width: 100%; border: 1px solid #dfe5ee; border-radius: 2mm; }}
figcaption {{ font-size: 9pt; color: #5a6678; margin-top: 1.5mm; }}
.butir {{ margin: 1mm 0; }}
.galeri {{ page-break-before: always; }}
</style></head><body>
<section class="sampul"><div class="kecil">DOKUMEN UAT</div><h1>Project &amp; Team Management<br>Admin App dan Team App</h1>
<p>Checklist penerimaan 17 area (Petunjuk Teknis 11.2), 8 skenario wajib (11.3), langkah uji per peran, bukti, dan temuan. Diuji 12 November 2026 pada instalasi bersih yang dipasang mengikuti README, data sintetis.</p>
<p>Laravel 13.42 · PHP 8.4 · MySQL 8.4 · Bootstrap 5.3 · Yajra DataTables 13 · SortableJS · FullCalendar</p></section>
<h2>Langkah uji per peran</h2><table><thead><tr><th>Peran</th><th>Langkah</th></tr></thead><tbody>{langkah}</tbody></table>
{isi}
<section class="galeri"><h2>Bukti tangkapan layar</h2>{gambar}</section>
</body></html>'''
open(os.path.join(DOCS, 'UAT.html'), 'w', encoding='utf-8', newline='\n').write(doc)
print('docs/UAT.html', len(doc))
