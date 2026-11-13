"""Timing map of cursorcam steps per recording: index, action, target, start/end second in video.mp4."""
import json
import os
import sys

OUT = os.environ.get('PTM_CURSORCAM_OUTPUT') or os.path.join(os.path.dirname(os.path.abspath(__file__)), 'cursorcam-output')
TAKES = [
    's1-create-project-20261112-064345-8f29',
    's2-kanban-20261112-071853-19fc',
    's3-putri-submit-20261112-072021-3c77',
    's4-radit-revision-20261112-072109-5a11',
    's5-putri-resubmit-20261112-072158-7b13',
    's6-radit-approve-20261112-072248-72d7',
    's7-meeting-deadline-20261112-070346-040a',
    's8-new-period-20261112-072356-2ba9',
    's9-team-desktop-403-20261112-070912-c313',
    's10-datatables-520-20261112-071036-9868',
]


def step_times(take):
    d = os.path.join(OUT, take)
    run = json.load(open(os.path.join(d, 'run.json'), encoding='utf-8'))
    steps = json.load(open(os.path.join(d, 'steps.json'), encoding='utf-8'))['steps']
    cache = run['cacheDir']
    with open(os.path.join(cache, 'frames.jsonl'), encoding='utf-8') as f:
        zero = json.loads(f.readline())['at']
    starts, ends = {}, {}
    with open(os.path.join(cache, 'events.jsonl'), encoding='utf-8') as f:
        for line in f:
            e = json.loads(line)
            if e.get('type') == 'step':
                (starts if e['phase'] == 'start' else ends)[e['index']] = (e['t'] - zero) / 1000
    duration = json.load(open(os.path.join(d, 'result.json'), encoding='utf-8'))['durationSeconds']
    return steps, starts, ends, duration


if __name__ == '__main__':
    for t in TAKES if len(sys.argv) < 2 else [x for x in TAKES if x.startswith(sys.argv[1] + '-')]:
        steps, starts, ends, duration = step_times(t)
        print(f'== {t} ({duration:.1f} s)')
        for i, s in enumerate(steps):
            k = next(iter(s))
            v = s[k] if not isinstance(s[k], dict) else json.dumps(s[k])
            print(f'{i:2d} {starts.get(i, -1):6.2f} {ends.get(i, -1):6.2f} {k:7s} {str(v)[:60]}')
