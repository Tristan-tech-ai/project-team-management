"""Peta waktu langkah cursorcam per rekaman: indeks, aksi, target, detik mulai/selesai di video.mp4."""
import json
import os
import sys

OUT = r'C:\Users\Tristan\Downloads\project-pkl\project-team-management\docs\video\cursorcam-output'
TAKES = [
    's1-buat-project-20261112-064345-8f29',
    's2-kanban-20261112-071853-19fc',
    's3-putri-kirim-20261112-072021-3c77',
    's4-radit-revisi-20261112-072109-5a11',
    's5-putri-ulang-20261112-072158-7b13',
    's6-radit-terima-20261112-072248-72d7',
    's7-rapat-deadline-20261112-070346-040a',
    's8-periode-baru-20261112-072356-2ba9',
    's9-tim-desktop-403-20261112-070912-c313',
    's10-datatables-520-20261112-071036-9868',
]


def peta(take):
    d = os.path.join(OUT, take)
    run = json.load(open(os.path.join(d, 'run.json'), encoding='utf-8'))
    langkah = json.load(open(os.path.join(d, 'steps.json'), encoding='utf-8'))['steps']
    cache = run['cacheDir']
    with open(os.path.join(cache, 'frames.jsonl'), encoding='utf-8') as f:
        nol = json.loads(f.readline())['at']
    mulai, akhir = {}, {}
    with open(os.path.join(cache, 'events.jsonl'), encoding='utf-8') as f:
        for baris in f:
            e = json.loads(baris)
            if e.get('type') == 'step':
                (mulai if e['phase'] == 'start' else akhir)[e['index']] = (e['t'] - nol) / 1000
    dur = json.load(open(os.path.join(d, 'result.json'), encoding='utf-8'))['durationSeconds']
    return langkah, mulai, akhir, dur


if __name__ == '__main__':
    for t in TAKES if len(sys.argv) < 2 else [x for x in TAKES if x.startswith(sys.argv[1] + '-')]:
        langkah, m, a, dur = peta(t)
        print(f'== {t} ({dur:.1f} dtk)')
        for i, s in enumerate(langkah):
            k = next(iter(s))
            v = s[k] if not isinstance(s[k], dict) else json.dumps(s[k])
            print(f'{i:2d} {m.get(i, -1):6.2f} {a.get(i, -1):6.2f} {k:7s} {str(v)[:60]}')
