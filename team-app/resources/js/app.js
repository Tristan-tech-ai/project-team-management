import * as bootstrap from 'bootstrap';
import { initConfirmations, fixAccessibility, showFlashMessages, initDemoLogin, initSidebar, initShortcuts, initTaskCreate, initAutoSubmit, initQuickDates, initSafeForms, initHints, initFilePickers, initFirstSteps, initTaskPanel, initPeopleFilter, initPicHandover, initNumberWheelGuard } from './core';

window.bootstrap = bootstrap;

// Heavy modules (DataTables + jQuery, SortableJS, FullCalendar) are loaded only on pages that use them.
document.addEventListener('DOMContentLoaded', () => {
    initConfirmations(bootstrap.Modal);
    showFlashMessages();
    fixAccessibility();
    initDemoLogin();
    initSidebar();
    initShortcuts();
    initFirstSteps();
    initFilePickers();
    initHints(bootstrap.Popover);
    initSafeForms();
    initQuickDates();
    initAutoSubmit();
    initTaskCreate();
    initPeopleFilter();
    initPicHandover();
    initNumberWheelGuard();
    // I3: task detail as a side panel; what the panel loads gets the same helpers as a normal page.
    initTaskPanel(bootstrap.Offcanvas, {
        onContent: (root) => {
            initFilePickers(root);
            initQuickDates(root);
            initHints(bootstrap.Popover, root);
            initPeopleFilter(root);
            import('./kanban').then((m) => m.initChecklist());
        },
    });
    if (document.querySelector('table[data-table]')) import('./table').then((m) => m.initTables());
    if (document.querySelector('[data-kanban], [data-check-subtask], [data-change-status], [data-fill-duration], [data-fill-days]')) {
        import('./kanban').then((m) => { m.initKanban(); m.initChecklist(); });
    }
    if (document.querySelector('[data-calendar]')) import('./calendar').then((m) => m.initCalendar());
    // Reopen the Quick Add modal when server validation fails, so typed input is not lost.
    const reopen = document.querySelector('[data-open-modal]');
    if (reopen) {
        const modal = document.getElementById(reopen.dataset.openModal);
        if (modal) new bootstrap.Modal(modal).show();
    }
});
