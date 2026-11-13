"""Release check for the handover zip ("D100" definition). Every check prints PASS or FAIL with the reason; exit code 1 on any FAIL.
Usage:
  python docs/tools/release-check.py --repo .                 checks the git checkout that the zip is built from
  python docs/tools/release-check.py --zip project.zip        checks the files inside a built zip (content checks only)
Checks that need a running system (clean install, PHPUnit, browser tests, online URLs) are recorded as evidence files;
this script verifies that the evidence exists, passes, matches TESTING.md, and is newer than the code."""
import argparse
import json
import os
import re
import subprocess
import sys
import tempfile
import xml.etree.ElementTree as ET
import zipfile

ap = argparse.ArgumentParser()
ap.add_argument('--repo')
ap.add_argument('--zip')
args = ap.parse_args()
results = []


def report(name, ok, detail=''):
    results.append(ok)
    print(f"{'PASS' if ok else 'FAIL'}  {name}" + (f'\n      {detail}' if detail else ''))


def run(cmd, cwd):
    return subprocess.run(cmd, cwd=cwd, capture_output=True, text=True, encoding='utf-8', errors='replace')


def file_list(root):
    out = []
    for d, dirs, fs in os.walk(root):
        dirs[:] = [x for x in dirs if x not in ('.git', 'vendor', 'node_modules')]
        for f in fs:
            out.append(os.path.relpath(os.path.join(d, f), root).replace('\\', '/'))
    return sorted(out)


# ---------- content checks (repo or zip) ----------
FORBIDDEN = [
    (r'(^|/)\.env$', '.env with real values'), (r'(^|/)\.env\.(?!example$)[^/]+$', 'environment file other than .env.example'),
    (r'\.sqlite$', 'SQLite database'), (r'(^|/)(vendor|node_modules)/', 'installed dependencies'),
    (r'(^|/)public/build/', 'built assets'), (r'(^|/)storage/logs/.+\.log$', 'log file'),
    (r'\.phpunit\.result\.cache$', 'PHPUnit cache'), (r'(^|/)bootstrap/cache/[^/]+\.php$', 'Laravel cache'),
    (r'(^|/)(CLAUDE|AGENTS|GEMINI)\.md$', 'assistant instruction file'), (r'(^|/)\.(claude|cursor|codex|aider)[^/]*/', 'assistant config folder'),
    (r'(^|/)docs/Petunjuk_Teknis[^/]*$', 'guide document with personal metadata (kept out of the public copy)'),
]
TRACE = re.compile(r'\b(claude|anthropic|copilot|chatgpt|openai|gemini)\b|co-authored-by|generated with', re.I)
SECRET = [re.compile(p) for p in (
    r'APP_KEY=base64:[A-Za-z0-9+/=]{20,}', r'-----BEGIN [A-Z ]*PRIVATE KEY-----', r'\bghp_[A-Za-z0-9]{20,}', r'\bsk-[A-Za-z0-9_-]{20,}',
    r'\bAKIA[0-9A-Z]{16}\b', r'mysql://[^:\s]+:[^@\s]+@', r'\bhf_[A-Za-z0-9]{20,}')]
TEXT = ('.php', '.js', '.mjs', '.css', '.py', '.sh', '.md', '.json', '.yml', '.yaml', '.xml', '.txt', '.sql', '.html', '.example',
        'Dockerfile', 'Caddyfile', '.gitignore', '.editorconfig', '.npmrc')
# Indonesian words that must not appear in identifiers, comments or code-like strings (UI text and data are allowed).
ID_WORDS = set('''ada adalah akhir aksi aktif aktifkan aktivitas aktual akun alamat alasan alat alur anggota antrian apa arsip asap aset asing
atas atau awal bagian baris baru batal beban belum beranda berikut berjalan berkas bersama berulang besar bisa boleh buat buka bukti bulan
cadangan cakupan cari catat catatan cek cetak contoh dampak dan dari dasar daftar di diajukan dibuat dicatat dikerjakan disetujui diterima
ditolak divisi dokumen durasi galat gambar ganda gerak geser gulir hambatan hapus hari harus hasil hitung ikon ikut impor induk ini inti isi
isian izin jabatan jadwal jalan jalur jam jenis judul jumlah kalender kartu kecil keluar kelas kepala kerja ket kirim kode kolom kondisi
konfirmasi konten kosong kunci lain lama lampiran langkah laporan lebar lencana lewat lihat lulus mati masuk menit menunggu merek milik mulai
nama navigasi nilai oleh orang pakai panjang pelaksana pembantu pengaturan pengguna penugasan peramban perintah periode persen peserta
pesan pilih pilihan pindah pratinjau progres publikasi rahasia rapat remah rentang revisi rincian riwayat ruang sama samping sandi sampai
satu saya sebelum sekarang selesai semua serius setuju siapkan sidik simpan sinkron skrip sumber susun tabel tambah tampil tampilan tanggal
tangkap tanpa tata tautan teks telat templat terakhir terlambat tetap tidak tiap tim tinjau tipe tolak tombol tugas ubah uji ukuran ulang
unduh untuk urut usul usulan utama waktu wajib yang zona'''.split())
ALLOW_LANG = ('/lang/id/', 'docs/tools/release-check.py')
STR = re.compile(r"'''.*?'''|\"\"\".*?\"\"\"|'(?:[^'\\\n]|\\.)*'|\"(?:[^\"\\\n]|\\.)*\"|`(?:[^`\\]|\\.)*`", re.S)
COM = re.compile(r'/\*.*?\*/|(?<![:\w])//[^\n]*|\{\{--.*?--\}\}|<!--.*?-->|^[ \t]*#(?![!\[])[^\n]*', re.S | re.M)
KEYCTX = re.compile(r"""(?:\[|,|\()\s*'([\w.*-]+)'\s*=>|->(?:input|filled|boolean|integer|query|has|file|string|date)\(\s*'([\w.*-]+)'|"""
                    r"""\b(?:route|old|request)\(\s*'([\w.*-]+)'|(?:required_with|required_without|required_if|same|different|"""
                    r"""prohibited_if|exclude_if|after|before)[_a-z]*:([\w.*]+)|\bin:([\w,]+)""")


def parts(w):
    w = re.sub(r'([a-z0-9])([A-Z])', r'\1 \2', w)
    return [p.lower() for p in re.split(r'[^A-Za-z]+', w) if p]


def lang_hits(rel, src):
    hits = []
    if rel.endswith('.md') or rel.endswith('.sql') or rel.endswith('.txt') or rel.endswith('.html') or any(a in '/' + rel for a in ALLOW_LANG):
        return hits
    if rel.startswith('docs/evidence/') or rel.endswith('.json') and not rel.endswith('package.json'):
        return hits

    def check(kind, token, line):
        bad = sorted({p for p in parts(token) if p in ID_WORDS})
        if bad:
            hits.append(f'{rel}:{line} {kind} {token!r} ({",".join(bad)})')

    for m in COM.finditer(src):
        # text in double quotes inside a comment quotes the user interface verbatim (UI text stays Indonesian)
        words = {w.lower() for w in re.findall(r'[A-Za-z]+', re.sub(r'"[^"\n]*"', ' ', m.group(0)))}
        bad = sorted(words & ID_WORDS & {'dan', 'yang', 'untuk', 'dengan', 'tidak', 'atau', 'dari', 'oleh', 'tanpa', 'harus', 'bisa', 'boleh',
                                         'sama', 'tetap', 'belum', 'sudah', 'hanya', 'setiap', 'tiap', 'semua', 'ini', 'pakai', 'lihat', 'bila',
                                         'saat', 'supaya', 'agar', 'kalau', 'jadi', 'akan', 'pada', 'sebagai', 'ke', 'di'} | (words & {
                                         'lencana', 'kartu', 'remah', 'berkas', 'lampiran', 'pengguna', 'sandi', 'tautan', 'tombol', 'ikon'}))
        if bad:
            hits.append(f'{rel}:{src.count(chr(10), 0, m.start()) + 1} comment ({",".join(bad)}) {m.group(0).strip()[:80]!r}')
    code = COM.sub(lambda m: re.sub(r'[^\n]', ' ', m.group(0)), src)
    # multi-line strings (template literals, docstrings, HTML in f-strings) hold UI or document text: blank them, keep line numbers
    code = STR.sub(lambda m: re.sub(r'[^\n]', ' ', m.group(0)) if '\n' in m.group(0) else m.group(0), code)
    blade = rel.endswith('.blade.php')

    def directive_args(line):
        out = []
        for m in re.finditer(r'@\w+\(', line):
            depth, j = 1, m.end()
            while j < len(line) and depth:
                depth += {'(': 1, ')': -1}.get(line[j], 0)
                j += 1
            out.append(line[m.end():j - 1])
        return out

    for i, line in enumerate(code.split('\n'), 1):
        if 'LEGACY_NAMES' in line:
            continue  # documented compatibility: old disk names stored in existing database rows
        if blade:
            for m in re.finditer(r'\b(class|id|name|for|form|data-[\w-]+)="([^"]*)"', line):
                v = re.sub(r'\{\{.*?\}\}', ' ', m.group(2))
                if m.group(1).startswith('data-') and re.search(r'\s', v.strip()):
                    continue
                for t in re.findall(r'[A-Za-z_][\w-]*', v):
                    check('attribute ' + m.group(1), t, i)
            for m in re.finditer(r'\bdata-[\w-]+', line):
                check('attribute', m.group(0), i)
            segs = [m.group(1) for m in re.finditer(r'\{\{(.*?)\}\}', line)] + directive_args(line)
        else:
            segs = [line]
        for seg in segs:
            if seg is None:
                continue
            for t in re.findall(r'[A-Za-z_$][\w$]*', STR.sub(' ', seg)):
                check('identifier', t, i)
            for m in KEYCTX.finditer(seg):
                for g in m.groups():
                    if g:
                        for t in re.split(r'[,.]', g):
                            check('key', t, i)
    # multi-line template strings and docstrings are skipped by design: they hold UI or document text
    return hits


def content_checks(root, label, tracked=None):
    files = tracked if tracked is not None else file_list(root)
    bad = [f'{f} ({why})' for f in files for pat, why in FORBIDDEN if re.search(pat, f)]
    report(f'{label}: no forbidden files (env, secrets, dependencies, caches, assistant files)', not bad, '; '.join(bad[:10]))
    traces, secrets, lang = [], [], []
    for f in files:
        if not f.endswith(TEXT) and '.' in os.path.basename(f):
            continue
        try:
            src = open(os.path.join(root, f), encoding='utf-8').read()
        except (UnicodeDecodeError, OSError):
            continue
        if f != 'docs/tools/release-check.py':
            traces += [f'{f}: {m.group(0)}' for m in TRACE.finditer(src)][:3]
        if f != 'docs/tools/release-check.py':  # this file holds the patterns themselves
            secrets += [f'{f}: {p.pattern}' for p in SECRET if p.search(src)]
        if f.endswith('.env.example'):
            secrets += [f'{f}: {ln.split("=")[0]} has a value' for ln in src.splitlines()
                        if re.match(r'^[A-Z_]*(PASSWORD|SECRET|_KEY|TOKEN)=(?!null$)\S', ln) and not ln.startswith(('APP_KEY=', 'VITE_'))]
        if f.endswith(('.php', '.js', '.mjs', '.css', '.py', '.sh', 'Dockerfile', '.yml', '.yaml', 'package.json')):
            lang += lang_hits(f, src)
    report(f'{label}: no assistant traces', not traces, '; '.join(traces[:10]))
    report(f'{label}: no secrets (keys, tokens, passwords in .env.example)', not secrets, '; '.join(secrets[:10]))
    report(f'{label}: code names, comments and code strings are English ({len(lang)} hits)', not lang, '\n      '.join(lang[:40]))
    return files


# ---------- repo-only checks ----------
def junit(path):
    r = ET.parse(path).getroot()
    s = r if r.tag == 'testsuite' else r.find('testsuite')
    return {k: int(float(s.get(k, 0))) for k in ('tests', 'failures', 'errors', 'skipped', 'assertions')}


def repo_checks(root):
    st = run(['git', 'status', '--porcelain'], root).stdout.strip()
    report('repo: working tree clean', not st, st[:300])
    head = run(['git', 'rev-parse', 'HEAD'], root).stdout.strip()
    up = run(['git', 'rev-parse', '@{u}'], root)
    report('repo: HEAD is pushed (equals upstream)', up.returncode == 0 and up.stdout.strip() == head,
           up.stderr.strip() or f'HEAD {head[:10]} upstream {up.stdout.strip()[:10]}')
    sync = run(['php', 'docs/tools/sync-domain.php', '--check'], root)
    report('repo: shared domain code identical in both apps', sync.returncode == 0, (sync.stdout + sync.stderr).strip()[-300:])
    ev = os.path.join(root, 'docs', 'evidence', 'tests')
    testing = open(os.path.join(ev, 'TESTING.md'), encoding='utf-8').read()
    for app in ('admin-app', 'team-app'):
        p = os.path.join(ev, f'phpunit-{app}.xml')
        if not os.path.exists(p):
            report(f'evidence: phpunit-{app}.xml exists', False)
            continue
        try:
            j = junit(p)
        except ET.ParseError as e:
            report(f'evidence: phpunit-{app}.xml is a complete JUnit report', False, str(e))
            continue
        report(f'evidence: PHPUnit {app} {j["tests"]} tests, {j["failures"]} failures, {j["errors"]} errors, {j["skipped"]} skipped',
               j['tests'] > 0 and j['failures'] == 0 and j['errors'] == 0 and j['skipped'] == 0)
        m = re.search(rf'`phpunit-{app}\.xml`[^\n]*\|\s*(\d+) lulus, (\d+) gagal', testing)
        report(f'evidence: TESTING.md states the same PHPUnit {app} numbers', bool(m) and int(m.group(1)) == j['tests'] and m.group(2) == '0',
               f'TESTING.md {m.groups() if m else "no row"} vs junit {j["tests"]}')
    flow = open(os.path.join(ev, 'e2e-flow.txt'), encoding='utf-8').read()
    m = re.search(r'(\d+) lulus, (\d+) gagal', flow)
    report('evidence: e2e flow all steps passed', bool(m) and m.group(2) == '0' and 'GAGAL' not in flow, m.group(0) if m else 'no summary')
    if m:
        t = re.search(r'`e2e-flow\.txt`[^\n]*\|\s*(\d+) lulus, (\d+) gagal', testing)
        report('evidence: TESTING.md states the same e2e numbers', bool(t) and t.group(1) == m.group(1), f'{t.groups() if t else "no row"} vs {m.group(0)}')
    for name in ('smoke-admin', 'smoke-team'):
        s = open(os.path.join(ev, f'{name}.txt'), encoding='utf-8').read()
        pages = len(re.findall(r'^ok\s+200 ', s, re.M))
        report(f'evidence: {name} {pages} pages, none failed', pages > 0 and 'GAGAL' not in s)
        t = re.search(rf'`{name}\.txt`[^\n]*\|\s*(\d+) halaman, 0 gagal', testing)
        report(f'evidence: TESTING.md states the same {name} page count', bool(t) and int(t.group(1)) == pages, f'{t.group(1) if t else "no row"} vs {pages}')
    ui = os.path.join(root, 'docs', 'evidence', 'ui')
    for f in sorted(x for x in os.listdir(ui) if x.startswith('audit-') and x.endswith('.json')):
        rows = json.load(open(os.path.join(ui, f), encoding='utf-8'))
        probs = [r for r in rows if r.get('serious') or (r.get('horizontalOverflow') or 0) > 1 or r.get('http') != 200
                 or r.get('smallTargets') or r.get('styleIssues')]
        measured = all(k in r for r in rows for k in ('serious', 'smallTargets', 'styleIssues'))
        report(f'evidence: UI audit {f} {len(rows)} checks (axe, click area, guide 6.1 sizes), {len(probs)} problems',
               bool(rows) and not probs and measured, '' if measured else 'old audit format without click-area and size measurements')
    guide = open(os.path.join(ev, 'GUIDE-AUDIT.md'), encoding='utf-8').read()
    statuses = [c.split('|')[3].strip() for c in guide.splitlines() if re.match(r'^\| \d+\.\d+', c)]
    open_items = [s for s in statuses if s not in ('Sesuai', 'Diperbaiki')]
    report(f'evidence: GUIDE-AUDIT {len(statuses)} statements, none open', bool(statuses) and not open_items, ', '.join(open_items))
    # every file a document points to exists
    missing = []
    for doc in ('README.md', 'docs/evidence/tests/TESTING.md', 'docs/evidence/tests/GUIDE-AUDIT.md', 'docs/evidence/ui/AUDIT.md',
                'docs/evidence/performance.md', 'docs/video/README.md'):
        p = os.path.join(root, doc)
        if not os.path.exists(p):
            missing.append(doc)
            continue
        text = open(p, encoding='utf-8').read()
        for ref in re.findall(r'\]\(([^)#\s]+)\)|`((?:\.\./|docs/|admin-app/|team-app/)?[\w./-]+\.(?:md|png|pdf|xml|txt|json|mp4|mjs|py|php|sql|sh))`', text):
            ref = ref[0] or ref[1]
            if ref.startswith('http'):
                continue
            bases = [os.path.dirname(p), root, os.path.join(root, 'docs', 'tools'), os.path.join(root, 'docs', 'evidence', 'tests'),
                     os.path.join(root, 'admin-app'), os.path.join(root, 'admin-app', 'resources', 'views'), os.path.join(root, 'team-app', 'resources', 'views')]
            if '*' not in ref and not any(os.path.exists(os.path.normpath(os.path.join(b, ref))) for b in bases):
                missing.append(f'{doc} -> {ref}')
    report('evidence: every file referenced in the documents exists', not missing, '; '.join(missing[:15]))
    code_time = int(run(['git', 'log', '-1', '--format=%ct', '--', 'admin-app', 'team-app'], root).stdout.strip() or 0)
    stale = []
    for f in ('docs/evidence/tests/phpunit-admin-app.xml', 'docs/evidence/tests/phpunit-team-app.xml', 'docs/evidence/tests/e2e-flow.txt',
              'docs/evidence/tests/smoke-admin.txt', 'docs/evidence/tests/smoke-team.txt', 'docs/evidence/tests/TESTING.md', 'docs/UAT.pdf'):
        t = int(run(['git', 'log', '-1', '--format=%ct', '--', f], root).stdout.strip() or 0)
        if t < code_time:
            stale.append(f)
    report('evidence: committed together with or after the last code change', not stale, ', '.join(stale))
    trees = {a: run(['git', 'rev-parse', f'HEAD:{a}'], root).stdout.strip()[:12] for a in ('admin-app', 'team-app')}
    stated = re.search(r'Kode yang diuji: admin-app `([0-9a-f]+)`, team-app `([0-9a-f]+)`', testing)
    report('evidence: TESTING.md names the exact code that was tested (git tree hashes)',
           bool(stated) and stated.group(1) == trees['admin-app'] and stated.group(2) == trees['team-app'],
           f'stated {stated.groups() if stated else "none"} vs HEAD {trees}')


if args.repo:
    root = os.path.abspath(args.repo)
    content_checks(root, 'repo', [t for t in run(['git', 'ls-files'], root).stdout.split('\n') if t])
    repo_checks(root)
if args.zip:
    with tempfile.TemporaryDirectory() as tmp:
        with zipfile.ZipFile(args.zip) as z:
            z.extractall(tmp)
        tops = os.listdir(tmp)
        report('zip: single top folder', len(tops) == 1, ', '.join(tops))
        inner = os.path.join(tmp, tops[0]) if len(tops) == 1 else tmp
        files = content_checks(inner, 'zip')
        if args.repo:
            tracked = sorted(run(['git', 'ls-files'], os.path.abspath(args.repo)).stdout.split('\n'))
            tracked = [t for t in tracked if t]
            report(f'zip: same files as the repo HEAD ({len(files)} vs {len(tracked)})', files == tracked,
                   ', '.join(sorted(set(files) ^ set(tracked)))[:300])
print(f"\n{results.count(True)} passed, {results.count(False)} failed")
sys.exit(0 if results and all(results) else 1)
