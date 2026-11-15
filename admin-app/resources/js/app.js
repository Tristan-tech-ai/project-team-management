import * as bootstrap from 'bootstrap';
import { initConfirmations, fixAccessibility, showFlashMessages, initDemoLogin, initSidebar, initShortcuts, initCodePreview, initQuickAdd, initRoleMatrix, initTaskCreate, initAutoSubmit, initQuickDates, initSafeForms, initCopyButtons, initHints, initFilePickers, initTodoBell, initFirstSteps } from './core';

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
    // The bell list loads when the browser is idle, so it never delays what the person does first on the page.
    (window.requestIdleCallback ?? ((fn) => setTimeout(fn, 1200)))(() => initTodoBell(), { timeout: 3000 });
    initFilePickers();
    initHints(bootstrap.Popover);
    initCopyButtons();
    initSafeForms();
    initQuickDates();
    initAutoSubmit();
    initTaskCreate();
    initCodePreview();
    initQuickAdd(bootstrap.Modal);
    initRoleMatrix();
    if (document.querySelector('table[data-table]')) import('./table').then((m) => m.initTables());
    if (document.querySelector('[data-kanban], [data-check-subtask], [data-change-status], [data-fill-duration], [data-fill-days]')) {
        import('./kanban').then((m) => { m.initKanban(); m.initChecklist(); });
    }
    if (document.querySelector('[data-calendar]')) import('./calendar').then((m) => m.initCalendar());
    if (document.querySelector('[data-template-builder]')) import('./template-builder').then((m) => m.initTemplateBuilder());
    if (document.querySelector('[data-menu-editor]')) import('./menu-editor').then((m) => m.initMenuEditor(bootstrap.Modal));
    if (document.querySelector('[data-form-project]')) import('./project-form').then((m) => m.initTemplatePreview());
    // Reopen the Quick Add modal when server validation fails, so typed input is not lost.
    const reopen = document.querySelector('[data-open-modal]');
    if (reopen) {
        const modal = document.getElementById(reopen.dataset.openModal);
        if (modal) new bootstrap.Modal(modal).show();
    }
});
