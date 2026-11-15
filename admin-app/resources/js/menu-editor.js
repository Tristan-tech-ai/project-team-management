// U6 Menu settings: drag and drop (SortableJS) for groups and pages, arrow buttons for keyboard users, a group select to move a
// page in one step, and an icon picker. The order is written into hidden inputs just before saving; the server validates everything.
import Sortable from 'sortablejs';

export function initMenuEditor(Modal) {
    const form = document.querySelector('[data-menu-editor]');
    if (!form) return;
    const groupsEl = form.querySelector('[data-sortable-groups]');
    const dirtyNote = form.querySelector('[data-dirty-note]');
    const markDirty = () => { if (dirtyNote) dirtyNote.hidden = false; };
    const listOf = (groupId) => form.querySelector(`[data-sortable-items][data-group-id="${groupId}"]`);

    const refresh = () => {
        form.querySelectorAll('.menu-group').forEach((group) => {
            const list = group.querySelector('[data-sortable-items]');
            const count = list.querySelectorAll('.menu-item-row').length;
            group.querySelector('[data-item-count]').textContent = `${count} menu`;
            group.querySelector('[data-empty-hint]').hidden = count > 0;
            list.querySelectorAll('[data-move-group]').forEach((select) => { select.value = list.dataset.groupId; });
        });
        markDirty();
    };

    new Sortable(groupsEl, { handle: '[data-group-handle]', animation: 150, ghostClass: 'card-ghost', onEnd: markDirty });
    form.querySelectorAll('[data-sortable-items]').forEach((list) => {
        new Sortable(list, { group: 'menu-items', handle: '[data-item-handle]', animation: 150, ghostClass: 'card-ghost', onEnd: refresh });
    });

    // Arrow buttons: move a group or a page one place up or down (keyboard and touch alternative to dragging).
    form.addEventListener('click', (e) => {
        const button = e.target.closest('[data-move]');
        if (!button) return;
        const row = button.closest('.menu-item-row') ?? button.closest('.menu-group');
        const sibling = button.dataset.move === 'up' ? row.previousElementSibling : row.nextElementSibling;
        if (!sibling || sibling.tagName !== row.tagName) return;
        if (button.dataset.move === 'up') sibling.before(row); else sibling.after(row);
        button.focus();
        refresh();
    });

    // Group select on a page row: moves the row to the end of the chosen group.
    form.addEventListener('change', (e) => {
        if (e.target.matches('[data-move-group]')) {
            const row = e.target.closest('.menu-item-row');
            const target = listOf(e.target.value);
            if (target && row.parentElement !== target) {
                target.appendChild(row);
                row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                row.classList.add('just-moved');
                setTimeout(() => row.classList.remove('just-moved'), 1200);
            }
            refresh();
        } else {
            markDirty();
        }
    });
    form.addEventListener('input', (e) => {
        markDirty();
        // Renaming a group renames it in every Group select too.
        if (e.target.matches('[data-group-title]')) {
            const id = e.target.closest('.menu-group').dataset.groupId;
            form.querySelectorAll(`[data-move-group] option[value="${id}"]`).forEach((o) => { o.textContent = e.target.value || '(tanpa nama)'; });
        }
    });

    // Order numbers follow the order on screen.
    form.addEventListener('submit', () => {
        form.querySelectorAll('.menu-group').forEach((group, i) => {
            group.querySelector(':scope > [data-order]').value = String((i + 1) * 10);
            group.querySelectorAll('.menu-item-row').forEach((row, j) => {
                row.querySelector('[data-order]').value = String((i + 1) * 100 + (j + 1) * 10);
            });
        });
    });

    // Icon picker: one dialog shared by every row.
    const iconModalEl = document.getElementById('icon-modal');
    if (iconModalEl) {
        const iconModal = new Modal(iconModalEl);
        let target = null;
        form.addEventListener('click', (e) => {
            const choice = e.target.closest('[data-icon-choice]');
            if (!choice) return;
            target = choice;
            const current = choice.nextElementSibling.value;
            iconModalEl.querySelectorAll('[data-icon]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.icon === current)));
            iconModal.show();
        });
        iconModalEl.addEventListener('click', (e) => {
            const option = e.target.closest('[data-icon]');
            if (!option || !target) return;
            target.nextElementSibling.value = option.dataset.icon;
            const svg = option.querySelector('svg').cloneNode(true);
            svg.setAttribute('width', '18');
            svg.setAttribute('height', '18');
            target.replaceChildren(svg);
            markDirty();
            iconModal.hide();
        });
        iconModalEl.addEventListener('hidden.bs.modal', () => target?.focus());
    }
}
