// Pratinjau task/subtask dari template sebelum generate (Petunjuk Teknis 8.1 langkah 3), juga menyaring PIC/anggota/divisi per company.
function escape(t) {
    const d = document.createElement('div');
    d.textContent = t ?? '';
    return d.innerHTML;
}

export function pasangPratinjauTemplate() {
    const form = document.querySelector('[data-form-project]');
    if (!form) return;
    const pilih = form.querySelector('[name="project_template_id"]');
    const mulai = form.querySelector('[name="start_at"]');
    const wadah = form.querySelector('[data-pratinjau]');
    const company = form.querySelector('[name="company_id"]');
    const tipe = form.querySelector('[name="project_type"]');
    const ulang = form.querySelector('[data-pengulangan]');

    async function muat() {
        if (!pilih || !wadah) return;
        if (!pilih.value) {
            wadah.innerHTML = '<p class="small teks-2 mb-0">Tanpa template: project dibuat kosong, task ditambah lewat Quick Add.</p>';
            return;
        }
        wadah.innerHTML = '<p class="small teks-2 mb-0">Memuat pratinjau...</p>';
        const q = new URLSearchParams({ template: pilih.value, mulai: mulai?.value ?? '' });
        try {
            const r = await fetch(`${form.dataset.urlPratinjau}?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const d = await r.json();
            const n = d.tasks.length;
            const ns = d.tasks.reduce((a, t) => a + t.subtasks.length, 0);
            wadah.innerHTML = `<p class="small mb-2"><strong>${n} task</strong> dan <strong>${ns} subtask</strong> akan dibuat (template ${escape(d.tipe)}).</p><ol class="small mb-0">${
                d.tasks.map((t) => `<li>${escape(t.title)}${t.due !== '-' ? ` <span class="teks-2">· deadline ${escape(t.due)}</span>` : ''}${
                    t.subtasks.length ? `<ul>${t.subtasks.map((s) => `<li>${escape(s)}</li>`).join('')}</ul>` : ''}</li>`).join('')}</ol>`;
        } catch {
            wadah.innerHTML = '<p class="small text-danger mb-0">Pratinjau gagal dimuat. Project tetap bisa dibuat.</p>';
        }
    }

    function saringCompany() {
        if (!company) return;
        const cid = company.value;
        form.querySelectorAll('[data-company]').forEach((el) => {
            const cocok = !cid || el.dataset.company === cid;
            el.hidden = !cocok;
            if (!cocok) el.querySelectorAll('input').forEach((i) => { i.checked = false; });
        });
        form.querySelectorAll('select[name="pic_user_id"] option[data-company]').forEach((o) => { o.hidden = cid && o.dataset.company !== cid; });
    }

    function tampilUlang() {
        if (ulang && tipe) ulang.hidden = tipe.value === 'one_time';
    }

    pilih?.addEventListener('change', muat);
    mulai?.addEventListener('change', muat);
    company?.addEventListener('change', saringCompany);
    tipe?.addEventListener('change', tampilUlang);
    muat();
    saringCompany();
    tampilUlang();
}
