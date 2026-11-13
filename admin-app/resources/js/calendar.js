// FullCalendar 6.1.22 (pinned, see docs/RESEARCH.md): four separate modes, data from GET /calendar/events.
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import idLocale from '@fullcalendar/core/locales/id';

const MODE_NOTES = {
    schedule: 'Deadline task dan aktivitas terjadwal.',
    activities: 'Rapat, kunjungan, revisi, dan hambatan menurut tanggal kejadian.',
    done: 'Task menurut tanggal selesai aktual (bukan tanggal terakhir diubah).',
    publication: 'Jadwal publikasi konten dan tanggal terbitnya.',
};

export function initCalendar() {
    const el = document.querySelector('[data-calendar]');
    if (!el) return;
    let mode = 'schedule';
    const isPhone = window.matchMedia('(max-width: 767.98px)').matches;
    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, listPlugin],
        locale: idLocale,
        timeZone: 'local',
        initialView: isPhone ? 'listMonth' : 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: isPhone ? '' : 'dayGridMonth,listMonth' },
        height: 'auto',
        events: (info, success, failure) => {
            const q = new URLSearchParams({ mode, start: info.startStr, end: info.endStr });
            if (el.dataset.project) q.set('project_id', el.dataset.project);
            if (el.dataset.mine === '1') q.set('mine', '1');
            fetch(`${el.dataset.calendar}?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error('Kalender gagal dimuat.'))))
                .then(success).catch(failure);
        },
        eventDidMount: (info) => {
            const p = info.event.extendedProps;
            info.el.title = [info.event.title, p.project, p.status].filter(Boolean).join(' · ');
        },
        noEventsContent: 'Tidak ada jadwal di rentang ini.',
        // Accessibility: day cell labels follow the visible text; arrow icons are decorative (buttons are labelled).
        dayCellDidMount: (info) => { info.el.removeAttribute('aria-labelledby'); },
        datesSet: () => { el.querySelectorAll('.fc-icon').forEach((i) => { i.setAttribute('aria-hidden', 'true'); i.removeAttribute('role'); }); },
    });
    calendar.render();
    document.querySelectorAll('[data-calendar-mode]').forEach((button) => button.addEventListener('click', () => {
        mode = button.dataset.calendarMode;
        document.querySelectorAll('[data-calendar-mode]').forEach((x) => {
            const active = x === button;
            x.classList.toggle('btn-primary', active);
            x.classList.toggle('btn-outline-primary', !active);
            x.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        const note = document.querySelector('[data-mode-note]');
        if (note) note.textContent = MODE_NOTES[mode];
        calendar.refetchEvents();
    }));
}
