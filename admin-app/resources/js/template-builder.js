// I11 template builder: the project template's tasks as rows that can be dragged into order. Each row has a title, when it is due,
// how often it repeats and its checklist. The rows are written back into the original "rows" textarea, so the server keeps one
// format ("Title | day | item; item | routine") and the form still works without JavaScript.
// 19 Nov (case study "Digital Marketing, repeat" and "Web PPDB"): a recurring template asks for the date of the month ("tgl 28")
// instead of a day offset, a task can repeat every day in a time window (one checklist item per day), a task can be repeated
// as numbered copies ("Konten 1" to "Konten 5"), and a written list can be pasted at once (indented or "-" lines become checklist items).
import Sortable from 'sortablejs';

const clean = (text) => text.replace(/[|;\n\r]+/g, ' ').trim();
const ROUTINES = { '': 'Sekali', daily: 'Setiap hari', weekdays: 'Setiap hari kerja (Sen-Jum)' };

function parse(text) {
    return text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => {
        const [title = '', day = '', subtasks = '', routine = ''] = line.split('|').map((p) => p.trim());
        const m = routine.match(/^(daily|weekdays)(?:\s+(\d{1,2}[:.]\d{2})\s*-\s*(\d{1,2}[:.]\d{2}))?$/);
        const time = (t) => (t ? t.replace('.', ':').padStart(5, '0') : '');
        return { title, day, subtasks: subtasks.split(';').map((s) => s.trim()).filter(Boolean), routine: m ? m[1] : '', from: time(m?.[2]), until: time(m?.[3]) };
    });
}

/** A pasted list: plain lines are tasks; lines that are indented further, or start with "-" when the tasks do not, become its checklist. */
export function parseList(text) {
    const lines = text.split(/\r?\n/).filter((l) => l.trim() !== '');
    const info = lines.map((l) => {
        const indent = l.match(/^\s*/)[0].replace(/\t/g, '    ').length;
        const bullet = /^\s*([-•*–]|\d+[.)])\s+/.test(l);
        return { indent, bullet, title: l.replace(/^\s*([-•*–]|\d+[.)])?\s*/, '').trim() };
    }).filter((x) => x.title);
    if (!info.length) return [];
    const minIndent = Math.min(...info.map((x) => x.indent));
    const mixedBullets = info.some((x) => x.bullet) && info.some((x) => !x.bullet && x.indent === minIndent);
    const tasks = [];
    info.forEach((x) => {
        const child = x.indent > minIndent || (mixedBullets && x.bullet && x.indent === minIndent);
        if (child && tasks.length) tasks[tasks.length - 1].subtasks.push(clean(x.title));
        else tasks.push({ title: clean(x.title), day: '', subtasks: [], routine: '', from: '', until: '' });
    });
    return tasks;
}

/** "Konten 1" -> ["Konten 1", ..., "Konten 5"]; a title without a number gets one ("Upload" -> "Upload 1"). */
export function numbered(title, count) {
    const m = title.match(/^(.*?)(\d+)(\D*)$/);
    const [head, start, tail] = m ? [m[1], Number(m[2]), m[3]] : [`${title} `, 1, ''];
    return Array.from({ length: count }, (_, i) => `${head}${start + i}${tail}`.trim());
}

export function initTemplateBuilder() {
    const textarea = document.querySelector('[data-template-builder]');
    if (!textarea) return;
    const form = textarea.form;
    const typeSelect = form?.querySelector('[name="project_type"]');
    const recurring = () => typeSelect && typeSelect.value !== 'one_time';
    const wrapper = document.createElement('div');
    wrapper.className = 'template-builder';
    wrapper.innerHTML = `<ol class="tb-list" data-tb-list></ol>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-tb-add>+ Tambah task</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-tb-paste-open aria-expanded="false" aria-controls="tb-paste">Tempel daftar</button>
        </div>
        <div class="tb-paste" id="tb-paste" hidden>
            <label class="form-label" for="tb-paste-text">Tempel atau ketik daftar task</label>
            <textarea class="form-control" id="tb-paste-text" rows="8" placeholder="Pembuatan DB&#10;Fitur publik&#10;- Landing page&#10;- Pendaftaran sekolah&#10;Debug dan QC"></textarea>
            <p class="small muted my-2">Satu task per baris. Baris yang diawali tanda - atau menjorok ke dalam menjadi checklist task di atasnya.</p>
            <div class="d-flex gap-2"><button type="button" class="btn btn-primary btn-sm" data-tb-paste-add>Tambahkan ke template</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-tb-paste-close>Batal</button></div>
        </div>`;
    textarea.insertAdjacentElement('beforebegin', wrapper);
    textarea.classList.add('visually-hidden');
    textarea.setAttribute('tabindex', '-1');
    document.getElementById('rows-b')?.setAttribute('hidden', '');
    const list = wrapper.querySelector('[data-tb-list]');
    let counter = 0;

    // The day input shows "tgl" for a recurring template (period starts on the 1st or on Monday) and "hari ke-" otherwise;
    // the textarea always keeps the day offset counted from the start (tgl 28 = 27).
    const dayLabel = () => (recurring() ? 'Deadline tgl' : 'Deadline hari ke-');
    const toShown = (offset) => (offset === '' ? '' : String(Number(offset) + (recurring() ? 1 : 0)));
    const toOffset = (shown) => (shown === '' ? '' : String(Math.max(0, Number(shown) - (recurring() ? 1 : 0))));
    const dayHint = (shown) => {
        if (shown === '') return 'Tanpa deadline: akhir periode.';
        if (recurring()) return `Setiap tanggal ${shown}.`;
        return Number(shown) === 0 ? 'Hari mulai project.' : `${shown} hari setelah mulai.`;
    };

    const write = () => {
        textarea.value = [...list.children].map((row) => {
            const title = clean(row.querySelector('[data-tb-title]').value);
            const routine = row.querySelector('[data-tb-routine]').value;
            const from = row.querySelector('[data-tb-from]').value;
            const until = row.querySelector('[data-tb-until]').value;
            const day = routine ? '' : row.dataset.offset ?? '';
            const subs = routine ? [] : row.querySelector('[data-tb-subs]').value.split(',').map(clean).filter(Boolean);
            const rule = routine ? `${routine}${from && until ? ` ${from}-${until}` : ''}` : '';
            const parts = [title, day, subs.join('; '), rule];
            while (parts.length > 1 && parts[parts.length - 1] === '') parts.pop();
            return title ? parts.join(' | ') : '';
        }).filter(Boolean).join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        list.querySelectorAll('[data-tb-number]').forEach((n, i) => { n.textContent = String(i + 1); });
    };

    const refreshRow = (row) => {
        const routine = row.querySelector('[data-tb-routine]').value;
        row.querySelector('[data-tb-day-label]').textContent = dayLabel();
        const dayInput = row.querySelector('[data-tb-day]');
        dayInput.max = recurring() ? '31' : '3650';
        dayInput.min = recurring() ? '1' : '0';
        row.querySelector('[data-tb-day-hint]').textContent = dayHint(dayInput.value);
        row.querySelector('[data-tb-when]').hidden = routine !== '';
        row.querySelector('[data-tb-subs-box]').hidden = routine !== '';
        row.querySelector('[data-tb-time]').hidden = routine === '';
        row.classList.toggle('is-routine', routine !== '');
    };

    const addRow = (task = { title: '', day: '', subtasks: [], routine: '', from: '', until: '' }, focus = false, after = null) => {
        const id = `tb${++counter}`;
        const row = document.createElement('li');
        row.className = 'tb-row';
        row.innerHTML = `
            <span class="drag-handle" data-tb-handle title="Seret untuk mengubah urutan" aria-hidden="true">⠿</span>
            <span class="tb-number" data-tb-number></span>
            <div class="tb-fields">
                <div class="tb-title"><label class="visually-hidden" for="${id}t">Judul task</label>
                    <input class="form-control" id="${id}t" data-tb-title maxlength="250" placeholder="Judul task, misalnya Desain UI/UX"></div>
                <div class="tb-repeat"><label class="form-label small mb-0" for="${id}r">Dikerjakan</label>
                    <select class="form-select" id="${id}r" data-tb-routine>${Object.entries(ROUTINES).map(([v, l]) => `<option value="${v}">${l}</option>`).join('')}</select></div>
                <div class="tb-day" data-tb-when><label class="form-label small mb-0" for="${id}d" data-tb-day-label></label>
                    <input class="form-control numeric" id="${id}d" data-tb-day type="number" inputmode="numeric" placeholder="-" aria-describedby="${id}h">
                    <div class="form-text mt-1" id="${id}h" data-tb-day-hint></div></div>
                <div class="tb-time" data-tb-time hidden><span class="form-label small mb-0 d-block" id="${id}w">Jam (boleh kosong)</span>
                    <div class="d-flex align-items-center gap-1"><label class="visually-hidden" for="${id}f">Jam mulai</label><input class="form-control" id="${id}f" type="time" data-tb-from>
                        <span aria-hidden="true">sampai</span><label class="visually-hidden" for="${id}u">Jam selesai</label><input class="form-control" id="${id}u" type="time" data-tb-until></div>
                    <div class="form-text mt-1">Checklist dibuat otomatis: satu per hari selama periode.</div></div>
                <div class="tb-subs" data-tb-subs-box><label class="form-label small mb-0" for="${id}s">Checklist (pisahkan dengan koma)</label>
                    <input class="form-control" id="${id}s" data-tb-subs placeholder="Misalnya Wireframe, Mockup"></div>
                <div class="tb-copy" data-tb-copy hidden>
                    <span class="small">Jadikan</span><label class="visually-hidden" for="${id}n">Jumlah task</label>
                    <input class="form-control form-control-sm numeric" id="${id}n" type="number" min="2" max="31" value="5" data-tb-copy-count>
                    <span class="small">task bernomor, jarak</span><label class="visually-hidden" for="${id}g">Jarak hari</label>
                    <input class="form-control form-control-sm numeric" id="${id}g" type="number" min="0" max="31" value="5" data-tb-copy-gap>
                    <span class="small">hari</span>
                    <button type="button" class="btn btn-primary btn-sm" data-tb-copy-make>Buat</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-tb-copy-close>Batal</button>
                </div>
            </div>
            <div class="move-buttons">
                <button type="button" class="btn btn-sm btn-outline-secondary tb-copy-open" data-tb-copy-open aria-label="Ulangi task ini jadi beberapa task bernomor">Ulangi</button>
                <button type="button" class="btn btn-icon" data-tb-move="up" aria-label="Naikkan task">↑</button>
                <button type="button" class="btn btn-icon" data-tb-move="down" aria-label="Turunkan task">↓</button>
                <button type="button" class="btn btn-icon text-danger" data-tb-remove aria-label="Hapus task">×</button>
            </div>`;
        row.querySelector('[data-tb-title]').value = task.title;
        row.dataset.offset = task.day;
        row.querySelector('[data-tb-day]').value = toShown(task.day);
        row.querySelector('[data-tb-subs]').value = task.subtasks.join(', ');
        row.querySelector('[data-tb-routine]').value = task.routine ?? '';
        row.querySelector('[data-tb-from]').value = task.from ?? '';
        row.querySelector('[data-tb-until]').value = task.until ?? '';
        if (after) after.after(row); else list.appendChild(row);
        refreshRow(row);
        if (focus) row.querySelector('[data-tb-title]').focus();
        return row;
    };

    const rows = parse(textarea.value);
    rows.forEach((t) => addRow(t));
    if (!list.children.length) addRow();
    write();
    new Sortable(list, { handle: '[data-tb-handle]', animation: 150, ghostClass: 'card-ghost', onEnd: write });
    list.addEventListener('input', (e) => {
        const row = e.target.closest('.tb-row');
        if (row && e.target.matches('[data-tb-day]')) row.dataset.offset = toOffset(e.target.value.trim());
        if (row && !e.target.closest('[data-tb-copy]')) refreshRow(row);
        write();
    });
    list.addEventListener('change', (e) => { const row = e.target.closest('.tb-row'); if (row) { refreshRow(row); write(); } });
    // Changing the template type keeps every deadline on the same day: only the way it is shown changes (offset vs. date).
    typeSelect?.addEventListener('change', () => list.querySelectorAll('.tb-row').forEach((row) => {
        row.querySelector('[data-tb-day]').value = toShown(row.dataset.offset ?? '');
        refreshRow(row);
    }));
    wrapper.querySelector('[data-tb-add]').addEventListener('click', () => { addRow(undefined, true); write(); });

    const paste = wrapper.querySelector('.tb-paste');
    const pasteOpen = wrapper.querySelector('[data-tb-paste-open]');
    const togglePaste = (open) => {
        paste.hidden = !open;
        pasteOpen.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) paste.querySelector('textarea').focus(); else pasteOpen.focus();
    };
    pasteOpen.addEventListener('click', () => togglePaste(paste.hidden));
    wrapper.querySelector('[data-tb-paste-close]').addEventListener('click', () => togglePaste(false));
    wrapper.querySelector('[data-tb-paste-add]').addEventListener('click', () => {
        const box = paste.querySelector('textarea');
        const tasks = parseList(box.value);
        if (!tasks.length) { box.focus(); return; }
        // An untouched empty first row is replaced instead of left above the pasted list.
        const only = list.children.length === 1 ? list.firstElementChild : null;
        if (only && !only.querySelector('[data-tb-title]').value.trim()) only.remove();
        tasks.forEach((t) => addRow(t));
        box.value = '';
        write();
        togglePaste(false);
        list.lastElementChild?.scrollIntoView({ block: 'nearest' });
    });

    list.addEventListener('click', (e) => {
        const row = e.target.closest('.tb-row');
        if (!row) return;
        if (e.target.closest('[data-tb-remove]')) {
            row.remove();
            if (!list.children.length) addRow();
            write();
            return;
        }
        const copy = row.querySelector('[data-tb-copy]');
        if (e.target.closest('[data-tb-copy-open]')) {
            copy.hidden = !copy.hidden;
            if (!copy.hidden) copy.querySelector('[data-tb-copy-count]').focus();
            return;
        }
        if (e.target.closest('[data-tb-copy-close]')) { copy.hidden = true; row.querySelector('[data-tb-copy-open]').focus(); return; }
        if (e.target.closest('[data-tb-copy-make]')) {
            const count = Math.min(31, Math.max(2, Number(copy.querySelector('[data-tb-copy-count]').value) || 2));
            const gap = Math.min(31, Math.max(0, Number(copy.querySelector('[data-tb-copy-gap]').value) || 0));
            const title = row.querySelector('[data-tb-title]').value.trim() || 'Task';
            const subs = row.querySelector('[data-tb-subs]').value.split(',').map(clean).filter(Boolean);
            const titles = numbered(title, count);
            const offset = row.dataset.offset ?? '';
            // Checklist items that carry the same number follow it ("Upload konten 1" -> "Upload konten 2").
            const number = (title.match(/(\d+)\D*$/) ?? [])[1];
            let last = row;
            titles.forEach((t, i) => {
                const n = String(Number(number ?? 1) + i);
                const items = subs.map((s) => (number && new RegExp(`\\b${number}\\b`).test(s) ? s.replace(new RegExp(`\\b${number}\\b`), n) : s));
                const day = offset === '' ? '' : String(Number(offset) + i * gap);
                if (i === 0) {
                    row.querySelector('[data-tb-title]').value = t;
                    row.querySelector('[data-tb-subs]').value = items.join(', ');
                    copy.hidden = true;
                    refreshRow(row);
                    return;
                }
                last = addRow({ title: t, day, subtasks: items, routine: row.querySelector('[data-tb-routine]').value,
                    from: row.querySelector('[data-tb-from]').value, until: row.querySelector('[data-tb-until]').value }, false, last);
            });
            write();
            last.querySelector('[data-tb-title]').focus();
            return;
        }
        const move = e.target.closest('[data-tb-move]');
        if (move) {
            const sibling = move.dataset.tbMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
            if (sibling) { if (move.dataset.tbMove === 'up') sibling.before(row); else sibling.after(row); move.focus(); write(); }
        }
    });
}
