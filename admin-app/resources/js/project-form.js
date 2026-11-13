// Preview of the tasks/subtasks a template will generate (Technical Guide 8.1 step 3); also filters PIC/members/divisions per company.
function escape(t) {
    const d = document.createElement('div');
    d.textContent = t ?? '';
    return d.innerHTML;
}

export function initTemplatePreview() {
    const form = document.querySelector('[data-form-project]');
    if (!form) return;
    const template = form.querySelector('[name="project_template_id"]');
    const start = form.querySelector('[name="start_at"]');
    const preview = form.querySelector('[data-preview]');
    const company = form.querySelector('[name="company_id"]');
    const type = form.querySelector('[name="project_type"]');
    const recurrence = form.querySelector('[data-recurrence]');

    async function load() {
        if (!template || !preview) return;
        if (!template.value) {
            preview.innerHTML = '<p class="small muted mb-0">Tanpa template: project dibuat kosong, task ditambah lewat Quick Add.</p>';
            return;
        }
        preview.innerHTML = '<p class="small muted mb-0">Memuat pratinjau...</p>';
        const q = new URLSearchParams({ template: template.value, start: start?.value ?? '' });
        try {
            const r = await fetch(`${form.dataset.previewUrl}?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const d = await r.json();
            const n = d.tasks.length;
            const ns = d.tasks.reduce((a, t) => a + t.subtasks.length, 0);
            preview.innerHTML = `<p class="small mb-2"><strong>${n} task</strong> dan <strong>${ns} subtask</strong> akan dibuat (template ${escape(d.type)}).</p><ol class="small mb-0">${
                d.tasks.map((t) => `<li>${escape(t.title)}${t.due !== '-' ? ` <span class="muted">· deadline ${escape(t.due)}</span>` : ''}${
                    t.subtasks.length ? `<ul>${t.subtasks.map((s) => `<li>${escape(s)}</li>`).join('')}</ul>` : ''}</li>`).join('')}</ol>`;
        } catch {
            preview.innerHTML = '<p class="small text-danger mb-0">Pratinjau gagal dimuat. Project tetap bisa dibuat.</p>';
        }
    }

    function filterByCompany() {
        if (!company) return;
        const companyId = company.value;
        form.querySelectorAll('[data-company]').forEach((el) => {
            const matches = !companyId || el.dataset.company === companyId;
            el.hidden = !matches;
            if (!matches) el.querySelectorAll('input').forEach((i) => { i.checked = false; });
        });
        form.querySelectorAll('select[name="pic_user_id"] option[data-company]').forEach((o) => { o.hidden = companyId && o.dataset.company !== companyId; });
    }

    function toggleRecurrence() {
        if (recurrence && type) recurrence.hidden = type.value === 'one_time';
    }

    template?.addEventListener('change', load);
    start?.addEventListener('change', load);
    company?.addEventListener('change', filterByCompany);
    type?.addEventListener('change', toggleRecurrence);
    load();
    filterByCompany();
    toggleRecurrence();
}
