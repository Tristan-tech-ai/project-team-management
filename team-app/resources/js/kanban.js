// Kanban (Technical Guide 9.4): SortableJS on desktop, status dropdown on phones, same endpoint PATCH /tasks/{id}/status.
// A new status only counts as saved after the API answers; when it is refused, the card goes back to its place with a message.
import Sortable from 'sortablejs';
import { sendJson, notify } from './core';

function updateCounts(board) {
    board.querySelectorAll('[data-kanban-column]').forEach((column) => {
        const n = column.querySelectorAll('[data-task-id]').length;
        const badge = board.querySelector(`[data-count="${column.dataset.status}"]`);
        if (badge) badge.textContent = String(n);
        const empty = column.querySelector('.kanban-empty');
        if (empty) empty.hidden = n > 0;
    });
}

function statusUrl(card) {
    return card.querySelector('[data-change-status]')?.dataset.changeStatus ?? `/tasks/${card.dataset.taskId}/status`;
}

function initBoard(board) {
    board.querySelectorAll('[data-kanban-column]').forEach((column) => {
        new Sortable(column, {
            group: 'tasks',
            animation: 150,
            // The whole card can be dragged, not only the grip: a press that moves less than 6 px is still a click on the
            // title or a button, and on a touch screen a short hold starts the drag so the board can still be scrolled.
            // Buttons, form fields and cards the person may not move never start a drag.
            draggable: '[data-task-id]',
            forceFallback: true,
            fallbackTolerance: 6,
            delay: 220,
            delayOnTouchOnly: true,
            filter: 'button, select, input, textarea, label, .card-actions, .task-card:not([data-movable])',
            preventOnFilter: false,
            ghostClass: 'card-ghost',
            onEnd: async (e) => {
                if (e.from === e.to) return;
                const card = e.item;
                const target = e.to.dataset.status;
                card.classList.add('is-saving');
                try {
                    const result = await sendJson(statusUrl(card), 'PATCH', { status: target });
                    card.dataset.status = result.status;
                    card.querySelector('.card-actions')?.remove(); // its next step belonged to the old column
                    const select = card.querySelector('[data-change-status]');
                    if (select) select.value = result.status;
                    notify(result.message);
                } catch (err) {
                    // Put the card back into its original column and position.
                    const anchor = e.from.querySelectorAll('[data-task-id]')[e.oldDraggableIndex] ?? e.from.querySelector('.kanban-empty');
                    e.from.insertBefore(card, anchor ?? null);
                    notify(err.message, 'error');
                } finally {
                    card.classList.remove('is-saving');
                    updateCounts(board);
                }
            },
        });
    });
}

let kanbanReady = false;
let checklistReady = false;

export function initKanban() {
    document.querySelectorAll('[data-kanban]').forEach(initBoard);
    if (kanbanReady) return; // page-wide listeners are added once (the side panel may load this module again)
    kanbanReady = true;
    // I3: a board refreshed after the side panel closed gets drag and drop again.
    document.addEventListener('ptm:region-replaced', (e) => {
        const region = e.detail;
        [region, ...region.querySelectorAll('[data-kanban]')].filter((x) => x.matches('[data-kanban]')).forEach(initBoard);
    });

    // Status dropdown (phones, or when dragging fails): same endpoint.
    document.addEventListener('change', async (e) => {
        const select = e.target;
        if (!(select instanceof HTMLSelectElement) || !select.dataset.changeStatus) return;
        const card = select.closest('[data-task-id]');
        const previous = card?.dataset.status ?? select.dataset.previous;
        select.disabled = true;
        try {
            const result = await sendJson(select.dataset.changeStatus, 'PATCH', { status: select.value });
            notify(result.message);
            const board = card?.closest('[data-kanban]');
            const destination = board?.querySelector(`[data-kanban-column][data-status="${result.status}"]`);
            if (card && destination) {
                card.dataset.status = result.status;
                destination.prepend(card);
                updateCounts(board);
            } else if (card) {
                card.dataset.status = result.status;
            }
        } catch (err) {
            select.value = previous;
            notify(err.message, 'error');
        } finally {
            select.disabled = false;
        }
    });
}

// Subtask checklist items are ticked directly without opening an edit page (Technical Guide 6.3).
export function initChecklist() {
    if (checklistReady) return;
    checklistReady = true;
    document.addEventListener('change', async (e) => {
        const box = e.target;
        if (!(box instanceof HTMLInputElement) || !box.dataset.checkSubtask) return;
        const label = box.closest('form')?.querySelector('label');
        box.disabled = true;
        try {
            const result = await sendJson(box.dataset.checkSubtask, 'PATCH', { done: box.checked });
            label?.classList.toggle('text-decoration-line-through', result.done);
            label?.classList.toggle('muted', result.done);
            document.querySelectorAll('[data-checklist-count]').forEach((el) => { el.textContent = result.total; });
            document.querySelectorAll('[data-checklist-progress]').forEach((el) => { el.style.width = `${result.progress}%`; });
            notify(result.message);
        } catch (err) {
            box.checked = !box.checked;
            notify(err.message, 'error');
        } finally {
            box.disabled = false;
        }
    });
    // This module loads after the page; on a slow phone a box ticked before that had no listener and the tick was lost
    // (seen online: "Rutin hari ini" looked ticked but was never saved). Send every box whose state already differs from the page.
    document.querySelectorAll('input[data-check-subtask]').forEach((box) => {
        if (box.checked !== box.defaultChecked) box.dispatchEvent(new Event('change', { bubbles: true }));
    });

    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-fill-duration]');
        if (!t) return;
        const field = document.querySelector(t.dataset.target);
        if (field) field.value = t.dataset.fillDuration;
    });

    // Quick deadline shift for a proposal: current deadline (selected task, else project) + N days, same time of day.
    // Without any deadline: today + N days at 17:00. Base values are already in the system display time zone.
    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-fill-days]');
        if (!t) return;
        const group = t.closest('[data-base-project]');
        let base = group?.dataset.baseProject ?? '';
        const select = group ? document.querySelector(group.dataset.selectTask) : null;
        if (select?.value) {
            try {
                base = JSON.parse(group.dataset.baseTask || '{}')[select.value] ?? base;
            } catch {
                // Broken map: keep the project deadline.
            }
        }
        const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(base);
        const d = m ? new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5])) : new Date();
        if (!m) d.setHours(17, 0, 0, 0);
        d.setDate(d.getDate() + Number(t.dataset.fillDays));
        const pad = (n) => String(n).padStart(2, '0');
        const field = document.querySelector(t.dataset.target);
        if (field) field.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    });
}
