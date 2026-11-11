import * as bootstrap from 'bootstrap';
import { pasangKonfirmasi, rapikanAksesibilitas, tampilkanFlash } from './inti';

window.bootstrap = bootstrap;

// Modul berat (DataTables + jQuery, SortableJS, FullCalendar) dimuat hanya di halaman yang memakainya.
document.addEventListener('DOMContentLoaded', () => {
    pasangKonfirmasi(bootstrap.Modal);
    tampilkanFlash();
    rapikanAksesibilitas();
    if (document.querySelector('table[data-tabel]')) import('./tabel').then((m) => m.pasangTabel());
    if (document.querySelector('[data-kanban], [data-centang], [data-ubah-status], [data-isi-durasi], [data-isi-hari]')) {
        import('./kanban').then((m) => { m.pasangKanban(); m.pasangChecklist(); });
    }
    if (document.querySelector('[data-kalender]')) import('./kalender').then((m) => m.pasangKalender());
    if (document.querySelector('[data-form-project]')) import('./project-form').then((m) => m.pasangPratinjauTemplate());
    // Buka kembali modal Quick Add bila validasi server gagal, supaya isian tidak hilang.
    const buka = document.querySelector('[data-buka-modal]');
    if (buka) {
        const m = document.getElementById(buka.dataset.bukaModal);
        if (m) new bootstrap.Modal(m).show();
    }
});
