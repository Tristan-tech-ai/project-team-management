// FullCalendar 6.1.22 (versi dikunci, lihat docs/RISET.md): empat mode terpisah, data dari GET /calendar/events.
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import idLocale from '@fullcalendar/core/locales/id';

const KET = {
    jadwal: 'Deadline task dan aktivitas terjadwal.',
    aktivitas: 'Rapat, kunjungan, revisi, dan hambatan menurut tanggal kejadian.',
    selesai: 'Task menurut tanggal selesai aktual (bukan tanggal terakhir diubah).',
    publikasi: 'Jadwal publikasi konten dan tanggal terbitnya.',
};

export function pasangKalender() {
    const el = document.querySelector('[data-kalender]');
    if (!el) return;
    let mode = 'jadwal';
    const hp = window.matchMedia('(max-width: 767.98px)').matches;
    const kal = new Calendar(el, {
        plugins: [dayGridPlugin, listPlugin],
        locale: idLocale,
        timeZone: 'local',
        initialView: hp ? 'listMonth' : 'dayGridMonth',
        headerToolbar: { left: 'prev,next today', center: 'title', right: hp ? '' : 'dayGridMonth,listMonth' },
        height: 'auto',
        events: (info, sukses, gagal) => {
            const q = new URLSearchParams({ mode, start: info.startStr, end: info.endStr });
            if (el.dataset.project) q.set('project_id', el.dataset.project);
            if (el.dataset.saya === '1') q.set('saya', '1');
            fetch(`${el.dataset.kalender}?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then((r) => (r.ok ? r.json() : Promise.reject(new Error('Kalender gagal dimuat.'))))
                .then(sukses).catch(gagal);
        },
        eventDidMount: (info) => {
            const p = info.event.extendedProps;
            info.el.title = [info.event.title, p.project, p.status].filter(Boolean).join(' · ');
        },
        noEventsContent: 'Tidak ada jadwal di rentang ini.',
    });
    kal.render();
    document.querySelectorAll('[data-mode-kalender]').forEach((b) => b.addEventListener('click', () => {
        mode = b.dataset.modeKalender;
        document.querySelectorAll('[data-mode-kalender]').forEach((x) => {
            const aktif = x === b;
            x.classList.toggle('btn-primary', aktif);
            x.classList.toggle('btn-outline-primary', !aktif);
            x.setAttribute('aria-pressed', aktif ? 'true' : 'false');
        });
        const ket = document.querySelector('[data-ket-mode]');
        if (ket) ket.textContent = KET[mode];
        kal.refetchEvents();
    }));
}
