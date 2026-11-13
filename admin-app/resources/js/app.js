import * as bootstrap from 'bootstrap';
import { initConfirmations, fixAccessibility, showFlashMessages } from './core';

window.bootstrap = bootstrap;

// Heavy modules (DataTables + jQuery, SortableJS, FullCalendar) are loaded only on pages that use them.
document.addEventListener('DOMContentLoaded', () => {
    initConfirmations(bootstrap.Modal);
    showFlashMessages();
    fixAccessibility();
    if (document.querySelector('table[data-table]')) import('./table').then((m) => m.initTables());
    if (document.querySelector('[data-kanban], [data-check-subtask], [data-change-status], [data-fill-duration], [data-fill-days]')) {
        import('./kanban').then((m) => { m.initKanban(); m.initChecklist(); });
    }
    if (document.querySelector('[data-calendar]')) import('./calendar').then((m) => m.initCalendar());
    if (document.querySelector('[data-form-project]')) import('./project-form').then((m) => m.initTemplatePreview());
    // Reopen the Quick Add modal when server validation fails, so typed input is not lost.
    const reopen = document.querySelector('[data-open-modal]');
    if (reopen) {
        const modal = document.getElementById(reopen.dataset.openModal);
        if (modal) new bootstrap.Modal(modal).show();
    }
});
