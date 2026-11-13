"""Builds the UAT document (print-ready HTML) from docs/evidence/tests/TESTING.md and the evidence screenshots.
Usage: python docs/tools/build-uat.py <test date, e.g. "13 November 2026">  -> docs/UAT.html
Print: node docs/tools/print-pdf.mjs docs/UAT.html docs/UAT.pdf
A missing screenshot stops the build, so the document never silently drops evidence."""
import html
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
DOCS = os.path.join(ROOT, 'docs')
FONT = os.environ.get('PTM_FONT_DIR', 'file:///C:/Users/Tristan/Downloads/project-pkl/proposal/fonts/')
if len(sys.argv) < 2:
    sys.exit('usage: python docs/tools/build-uat.py "<test date>"')
TEST_DATE = sys.argv[1]
LOCK = {p['name']: p['version'].lstrip('v') for p in json.load(open(os.path.join(ROOT, 'admin-app', 'composer.lock'), encoding='utf-8'))['packages']}
PKG = json.load(open(os.path.join(ROOT, 'admin-app', 'package.json'), encoding='utf-8'))
DEPS = {**PKG.get('dependencies', {}), **PKG.get('devDependencies', {})}
STACK = (f"Laravel {LOCK['laravel/framework']} · Yajra DataTables {LOCK['yajra/laravel-datatables-oracle']} · "
         f"Bootstrap {DEPS.get('bootstrap', '?')} · SortableJS {DEPS.get('sortablejs', '?')} · FullCalendar {DEPS.get('@fullcalendar/core', '?')}")


def inline(t):
    t = html.escape(t)
    t = re.sub(r'`([^`]+)`', r'<code>\1</code>', t)
    return re.sub(r'\*\*([^*]+)\*\*', r'<strong>\1</strong>', t)


def markdown_to_html(md):
    out, table = [], []

    def close():
        if table:
            header = [c.strip() for c in table[0].strip('|').split('|')]
            body = [[c.strip() for c in r.strip('|').split('|')] for r in table[2:]]
            out.append('<table><thead><tr>' + ''.join(f'<th>{inline(c)}</th>' for c in header) + '</tr></thead><tbody>'
                       + ''.join('<tr>' + ''.join(f'<td class="{"passed" if c == "Lulus" else ""}">{inline(c)}</td>' for c in r) + '</tr>' for r in body)
                       + '</tbody></table>')
            table.clear()
    for line in md.splitlines():
        if line.startswith('|'):
            table.append(line)
            continue
        close()
        if line.startswith('# '):
            continue
        if line.startswith('## '):
            out.append(f'<h2>{inline(line[3:])}</h2>')
        elif re.match(r'^\d+\. ', line):
            out.append(f'<p class="item">{inline(line)}</p>')
        elif line.strip():
            out.append(f'<p>{inline(line)}</p>')
    close()
    return '\n'.join(out)


STEPS = [
    ('Super Admin', 'Masuk ke Admin dengan akun Super Admin dari INITIAL_ADMIN_EMAIL. Buka setiap menu (9 menu, 23 submenu). Tambah Company, Client, Divisi, Jabatan, dan Anggota Tim. Ubah izin role Manager di Roles & Permissions, atur urutan menu, ubah zona waktu, buka Log aktivitas.'),
    ('Manager', 'Masuk ke Admin sebagai demo.manager. Buat project dari template dengan tiga divisi, cek pratinjau. Seret task di Kanban, tinjau hasil di Menunggu Review (revisi beralasan, lalu terima), setujui perubahan deadline, buka periode baru project berulang, ekspor laporan.'),
    ('Member', 'Masuk ke aplikasi Tim di HP sebagai demo.member. Buka Task Saya, ubah status lewat pilihan di kartu, centang checklist, kirim tautan dan berkas hasil, catat aktivitas. Coba buka alamat /management dan /settings/roles di Admin: harus muncul halaman tidak berizin.'),
]

SCREENSHOTS = [
    ('tests/e2e/01-new-board.png', 'Skenario 1: project dari template, 5 task di Antrian, 3 divisi'),
    ('tests/e2e/02-rollback.png', 'Kanban: seret ke Selesai ditolak server, kartu kembali'),
    ('tests/e2e/03-team-board-390.png', 'Aplikasi Tim 390 px: bottom navigation dan pilihan status'),
    ('tests/e2e/04-team-task-review-390.png', 'Aplikasi Tim 390 px: hasil (tautan + berkas) dikirim, task masuk Review'),
    ('tests/e2e/05-activity-schedule.png', 'Skenario 4: rapat tambahan dan deadline baru disetujui, target awal tetap'),
    ('tests/e2e/06-new-period.png', 'Skenario 5: periode baru project berulang'),
    ('tests/e2e/07-team-1366.png', 'Skenario 6: aplikasi Tim 1366 px tanpa bottom navigation'),
    ('tests/e2e/08-member-403.png', 'Skenario 7: Member memaksa URL Admin, 403'),
    ('tests/e2e/09-datatables-520.png', 'Skenario 8: DataTables 520 baris, paging di server'),
]
missing = [p for p, _ in SCREENSHOTS if not os.path.exists(os.path.join(DOCS, 'evidence', p))]
if missing:
    sys.exit('missing evidence screenshots: ' + ', '.join(missing))

content = markdown_to_html(open(os.path.join(DOCS, 'evidence', 'tests', 'TESTING.md'), encoding='utf-8').read())
steps = ''.join(f'<tr><td><strong>{role}</strong></td><td>{inline(text)}</td></tr>' for role, text in STEPS)
gallery = ''.join(f'<figure><img src="evidence/{p}" alt="{html.escape(c)}"><figcaption>{html.escape(c)}</figcaption></figure>'
                  for p, c in SCREENSHOTS)
doc = f'''<!doctype html><html lang="id"><head><meta charset="utf-8"><title>UAT Project & Team Management</title><style>
@font-face {{ font-family: PJS; src: url("{FONT}PlusJakartaSans-Regular.ttf"); font-weight: 400; }}
@font-face {{ font-family: PJS; src: url("{FONT}PlusJakartaSans-Bold.ttf"); font-weight: 700; }}
@page {{ size: A4; margin: 18mm 16mm 20mm; }}
body {{ font-family: PJS, sans-serif; color: #142033; font-size: 10.5pt; line-height: 1.45; }}
.cover {{ height: 240mm; display: flex; flex-direction: column; justify-content: center; page-break-after: always; }}
.cover .eyebrow {{ color: #2557d6; font-weight: 700; letter-spacing: .04em; font-size: 10pt; }}
.cover h1 {{ font-size: 28pt; margin: 6mm 0 4mm; line-height: 1.15; }}
.cover p {{ color: #5a6678; font-size: 11pt; max-width: 150mm; }}
h2 {{ font-size: 14pt; margin: 8mm 0 3mm; page-break-after: avoid; }}
table {{ width: 100%; border-collapse: collapse; margin: 2mm 0 4mm; font-size: 9pt; }}
th, td {{ text-align: left; vertical-align: top; border-bottom: 1px solid #dfe5ee; padding: 1.8mm 2mm; }}
th {{ color: #5a6678; font-weight: 700; border-bottom: 1.5px solid #c9d2df; }}
tr {{ page-break-inside: avoid; }}
td.passed {{ color: #17713f; font-weight: 700; }}
code {{ font-size: 8.5pt; background: #f2f5f9; padding: 0 1mm; border-radius: 1mm; word-break: break-word; }}
figure {{ margin: 0 0 6mm; page-break-inside: avoid; }}
figure img {{ width: 100%; border: 1px solid #dfe5ee; border-radius: 2mm; }}
figcaption {{ font-size: 9pt; color: #5a6678; margin-top: 1.5mm; }}
.item {{ margin: 1mm 0; }}
.gallery {{ page-break-before: always; }}
</style></head><body>
<section class="cover"><div class="eyebrow">DOKUMEN UAT</div><h1>Project &amp; Team Management<br>Admin App dan Team App</h1>
<p>Checklist penerimaan 17 area (Petunjuk Teknis 11.2), 8 skenario wajib (11.3), langkah uji per peran, bukti, dan temuan. Diuji {html.escape(TEST_DATE)}, data sintetis.</p>
<p>{html.escape(STACK)}</p></section>
<h2>Langkah uji per peran</h2><table><thead><tr><th>Peran</th><th>Langkah</th></tr></thead><tbody>{steps}</tbody></table>
{content}
<section class="gallery"><h2>Bukti tangkapan layar</h2>{gallery}</section>
</body></html>'''
open(os.path.join(DOCS, 'UAT.html'), 'w', encoding='utf-8', newline='\n').write(doc)
print('docs/UAT.html', len(doc), 'screenshots', len(SCREENSHOTS))
