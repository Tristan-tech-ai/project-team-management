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
    // Sent with the form: 1 = do not create template tasks whose deadline is already over (chosen in the preview).
    const skipPast = Object.assign(document.createElement('input'), { type: 'hidden', name: 'skip_past', value: '0' });
    form.appendChild(skipPast);

    async function load() {
        if (!template || !preview) return;
        if (!template.value) {
            preview.innerHTML = '<p class="small muted mb-0">Tanpa template: project dibuat kosong, task ditambah lewat Quick Add.</p>';
            return;
        }
        preview.innerHTML = '<p class="small muted mb-0">Memuat pratinjau...</p>';
        const unit = form.querySelector('[name="recurrence_unit"]');
        const q = new URLSearchParams({ template: template.value, start: start?.value ?? '', type: type?.dataset.touched === '1' ? type.value : '', unit: unit?.value ?? '' });
        try {
            const r = await fetch(`${form.dataset.previewUrl}?${q}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const d = await r.json();
            // Smart default (U9): the project type follows the template until the person picks a type themselves.
            if (type && d.project_type && type.dataset.touched !== '1' && type.value !== d.project_type) {
                type.value = d.project_type;
                type.dispatchEvent(new Event('change', { bubbles: true }));
            }
            // A recurring project started in the middle of its period begins with that period, so early deadlines can be over already.
            // The person chooses: skip those tasks (start now with what is still ahead) or start with the next period.
            const begin = start?.value ? new Date(start.value) : new Date();
            begin.setHours(0, 0, 0, 0);
            const isPast = (t) => !t.routine && t.due_at && new Date(t.due_at) < begin;
            const past = type?.value !== 'one_time' ? d.tasks.filter(isPast).length : 0;
            if (!past) skipPast.value = '0';
            const skipping = skipPast.value === '1';
            const shown = d.tasks.filter((t) => !(skipping && isPast(t)));
            const ns = shown.reduce((a, t) => a + t.subtasks.length, 0);
            const routines = shown.filter((t) => t.routine).length;
            const routineNote = routines ? ', termasuk <strong>' + routines + ' task rutin</strong> dengan checklist per hari' : '';
            const skipNote = skipping ? '; ' + past + ' task yang sudah lewat dilewati' : '';
            preview.innerHTML = `<p class="small mb-2"><strong>${shown.length} task</strong> dan <strong>${ns} subtask</strong> akan dibuat (template ${escape(d.type)})${routineNote}${skipNote}.</p><ol class="small mb-0">${
                d.tasks.map((t) => `<li class="${skipping && isPast(t) ? 'text-decoration-line-through muted' : ''}">${escape(t.title)}${t.routine ? ` <span class="pill pill-plain pill-info">${escape(t.routine)}</span>`
                    : (t.due !== '-' ? ` <span class="muted">· deadline ${escape(t.due)}</span>` : '')}${
                    t.subtasks.length ? `<ul>${t.subtasks.map((x) => `<li>${escape(x)}</li>`).join('')}</ul>` : ''}</li>`).join('')}</ol>`;
            if (past && start) {
                const weekly = form.querySelector('[name="recurrence_unit"]')?.value === 'week';
                const now = new Date();
                const next = weekly ? new Date(now.getFullYear(), now.getMonth(), now.getDate() + ((8 - now.getDay()) % 7 || 7), 8, 0)
                    : new Date(now.getFullYear(), now.getMonth() + 1, 1, 8, 0);
                const pad = (x) => String(x).padStart(2, '0');
                const value = `${next.getFullYear()}-${pad(next.getMonth() + 1)}-${pad(next.getDate())}T08:00`;
                const label = next.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
                const note = document.createElement('div');
                note.className = 'preview-warning mt-3';
                note.innerHTML = skipping
                    ? `<p class="small mb-2"><strong>${past} task dilewati</strong> karena deadlinenya sudah lewat. Task rutin mulai hari ini.</p>`
                    : `<p class="small mb-2"><strong>${past} deadline sudah lewat</strong> karena periode ${weekly ? 'minggu' : 'bulan'} ini sudah berjalan.</p>`;
                const actions = document.createElement('div');
                actions.className = 'd-flex flex-wrap gap-2';
                const skip = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn-sm btn-outline-primary',
                    textContent: skipping ? 'Buat semua task' : 'Lewati yang sudah lewat' });
                skip.addEventListener('click', () => { skipPast.value = skipping ? '0' : '1'; load(); });
                const later = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn-sm btn-outline-secondary', textContent: `Mulai periode ${label}` });
                later.addEventListener('click', () => { skipPast.value = '0'; start.value = value; start.dispatchEvent(new Event('change', { bubbles: true })); });
                actions.append(skip, later);
                note.appendChild(actions);
                preview.appendChild(note);
            }
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
    // A change made by the person (not by the template default above) is kept.
    type?.addEventListener('input', () => { type.dataset.touched = '1'; load(); });
    // Deadlines in the preview follow the first period (start of the month or week) of a recurring project.
    form.querySelector('[name="recurrence_unit"]')?.addEventListener('change', load);
    load();
    filterByCompany();
    toggleRecurrence();
}
