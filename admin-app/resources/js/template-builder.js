// I11 template builder: the project template's tasks as rows that can be dragged into order, each with a title, the day it is due
// (counted from the project or period start) and its checklist. The rows are written back into the original "rows" textarea, so the
// server keeps one format ("Title | day | item; item") and the form still works without JavaScript.
import Sortable from 'sortablejs';

const clean = (text) => text.replace(/[|;\n\r]+/g, ' ').trim();

function parse(text) {
    return text.split(/\r?\n/).map((line) => line.trim()).filter(Boolean).map((line) => {
        const [title = '', day = '', subtasks = ''] = line.split('|').map((p) => p.trim());
        return { title, day, subtasks: subtasks.split(';').map((s) => s.trim()).filter(Boolean) };
    });
}

export function initTemplateBuilder() {
    const textarea = document.querySelector('[data-template-builder]');
    if (!textarea) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'template-builder';
    wrapper.innerHTML = `<ol class="tb-list" data-tb-list></ol>
        <button type="button" class="btn btn-outline-primary btn-sm" data-tb-add>+ Tambah task</button>`;
    textarea.insertAdjacentElement('beforebegin', wrapper);
    textarea.classList.add('visually-hidden');
    textarea.setAttribute('tabindex', '-1');
    document.getElementById('rows-b')?.setAttribute('hidden', '');
    const list = wrapper.querySelector('[data-tb-list]');
    let counter = 0;

    const write = () => {
        textarea.value = [...list.children].map((row) => {
            const title = clean(row.querySelector('[data-tb-title]').value);
            const day = row.querySelector('[data-tb-day]').value.trim();
            const subs = row.querySelector('[data-tb-subs]').value.split(',').map(clean).filter(Boolean);
            const parts = [title, day, subs.join('; ')];
            while (parts.length > 1 && parts[parts.length - 1] === '') parts.pop();
            return title ? parts.join(' | ') : '';
        }).filter(Boolean).join('\n');
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
        list.querySelectorAll('[data-tb-number]').forEach((n, i) => { n.textContent = String(i + 1); });
    };

    const addRow = (task = { title: '', day: '', subtasks: [] }, focus = false) => {
        const id = `tb${++counter}`;
        const row = document.createElement('li');
        row.className = 'tb-row';
        row.innerHTML = `
            <span class="drag-handle" data-tb-handle title="Seret untuk mengubah urutan" aria-hidden="true">⠿</span>
            <span class="tb-number" data-tb-number></span>
            <div class="tb-fields">
                <div class="tb-title"><label class="visually-hidden" for="${id}t">Judul task</label>
                    <input class="form-control" id="${id}t" data-tb-title maxlength="250" placeholder="Judul task, misalnya Desain UI/UX"></div>
                <div class="tb-day"><label class="form-label small mb-0" for="${id}d">Deadline hari ke-</label>
                    <input class="form-control numeric" id="${id}d" data-tb-day type="number" min="0" max="3650" placeholder="-"></div>
                <div class="tb-subs"><label class="form-label small mb-0" for="${id}s">Checklist (pisahkan dengan koma)</label>
                    <input class="form-control" id="${id}s" data-tb-subs placeholder="Misalnya Wireframe, Mockup"></div>
            </div>
            <div class="move-buttons">
                <button type="button" class="btn btn-icon" data-tb-move="up" aria-label="Naikkan task">↑</button>
                <button type="button" class="btn btn-icon" data-tb-move="down" aria-label="Turunkan task">↓</button>
                <button type="button" class="btn btn-icon text-danger" data-tb-remove aria-label="Hapus task">×</button>
            </div>`;
        row.querySelector('[data-tb-title]').value = task.title;
        row.querySelector('[data-tb-day]').value = task.day;
        row.querySelector('[data-tb-subs]').value = task.subtasks.join(', ');
        list.appendChild(row);
        if (focus) row.querySelector('[data-tb-title]').focus();
        write();
    };

    parse(textarea.value).forEach((t) => addRow(t));
    if (!list.children.length) addRow();
    new Sortable(list, { handle: '[data-tb-handle]', animation: 150, ghostClass: 'card-ghost', onEnd: write });
    list.addEventListener('input', write);
    wrapper.querySelector('[data-tb-add]').addEventListener('click', () => addRow(undefined, true));
    list.addEventListener('click', (e) => {
        const row = e.target.closest('.tb-row');
        if (!row) return;
        if (e.target.closest('[data-tb-remove]')) {
            row.remove();
            if (!list.children.length) addRow();
            write();
        }
        const move = e.target.closest('[data-tb-move]');
        if (move) {
            const sibling = move.dataset.tbMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
            if (sibling) { if (move.dataset.tbMove === 'up') sibling.before(row); else sibling.after(row); move.focus(); write(); }
        }
    });
}
