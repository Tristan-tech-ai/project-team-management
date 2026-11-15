// Shared by admin-app and team-app: CSRF-protected JSON requests, short notifications, confirmation for risky actions.
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function sendJson(url, method = 'POST', body = {}) {
    const response = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
        body: method === 'GET' ? undefined : JSON.stringify(body),
        credentials: 'same-origin',
    });
    let data = {};
    try { data = await response.json(); } catch { data = {}; }
    if (!response.ok) {
        const message = data.message || (data.errors ? Object.values(data.errors).flat()[0] : null) || 'Perubahan tidak tersimpan. Coba lagi.';
        const error = new Error(message);
        error.status = response.status;
        error.errors = data.errors ?? {};
        throw error;
    }
    return data;
}

export function notify(message, kind = 'success') {
    let area = document.querySelector('.toast-area');
    if (!area) {
        area = document.createElement('div');
        area.className = 'toast-area';
        area.setAttribute('aria-live', 'polite');
        document.body.appendChild(area);
    }
    const el = document.createElement('div');
    el.className = `alert alert-${kind === 'error' ? 'danger' : 'success'} shadow-sm mb-2`;
    el.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    el.textContent = message;
    area.appendChild(el);
    setTimeout(() => el.remove(), kind === 'error' ? 6000 : 2800);
}

// <form data-confirm="Nonaktifkan Company A?"> asks for confirmation in a Bootstrap modal (never window.confirm()).
export function initConfirmations(Modal) {
    const modalEl = document.getElementById('confirm-modal');
    if (!modalEl) return;
    const modal = new Modal(modalEl);
    let pendingForm = null;
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
        e.preventDefault();
        pendingForm = form;
        modalEl.querySelector('[data-content]').textContent = form.dataset.confirm;
        modal.show();
    });
    modalEl.querySelector('[data-confirm-yes]')?.addEventListener('click', () => {
        if (!pendingForm) return;
        pendingForm.dataset.confirmed = '1';
        modal.hide();
        pendingForm.requestSubmit();
    });
}

// Flash messages from the server are shown as short notifications.
export function showFlashMessages() {
    document.querySelectorAll('[data-flash]').forEach((el) => {
        notify(el.dataset.flash, el.dataset.kind || 'success');
        el.remove();
    });
}

// U4: preview of the automatic code while the name is typed (same rule as App\Support\CodeGenerator::base).
const LEGAL_FORMS = ['PT', 'CV', 'UD', 'TBK', 'PERSERO', 'FIRMA', 'KOPERASI', 'YAYASAN'];
export function codeFromName(name) {
    const words = name.normalize('NFKD').replace(/[̀-ͯ]/g, '').toUpperCase().split(/[^A-Z0-9]+/).filter((w) => w && !LEGAL_FORMS.includes(w));
    let code = '';
    for (const w of words) {
        const next = code ? `${code}-${w}` : w;
        if (next.length > 24) break;
        code = next;
    }
    return code || (words[0] ?? '').slice(0, 24);
}

export function initCodePreview() {
    document.querySelectorAll('[data-code-from]').forEach((wrapper) => {
        const input = wrapper.querySelector('input');
        const source = input?.form?.querySelector(`[name="${wrapper.dataset.codeFrom}"]`);
        if (!input || !source) return;
        const update = () => { const code = codeFromName(source.value); input.placeholder = code ? `Otomatis: ${code}` : 'Otomatis dari nama'; };
        source.addEventListener('input', update);
        update();
    });
}

// U5: quick add of a missing master record (company, client) inside another form; the new record is selected right away.
export function initQuickAdd(Modal) {
    const modalEl = document.getElementById('quick-add-modal');
    if (!modalEl) return;
    const modal = new Modal(modalEl);
    const form = modalEl.querySelector('form');
    const general = form.querySelector('[data-error-general]');
    let trigger = null;
    const clearErrors = () => {
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('[data-error-for]').forEach((el) => { el.textContent = ''; });
        general.classList.add('d-none');
    };
    document.addEventListener('click', (e) => {
        const button = e.target.closest('[data-quick-add]');
        if (!button) return;
        trigger = button;
        form.reset();
        clearErrors();
        modalEl.querySelector('[data-quick-add-title]').textContent = button.dataset.title;
        modal.show();
    });
    modalEl.addEventListener('shown.bs.modal', () => form.querySelector('[name=name]').focus());
    modalEl.addEventListener('hidden.bs.modal', () => trigger?.focus());
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        const submit = form.querySelector('[type=submit]');
        submit.disabled = true;
        try {
            const result = await sendJson(trigger.dataset.url, 'POST', { ...Object.fromEntries(new FormData(form)), is_active: 1 });
            const select = document.querySelector(trigger.dataset.target);
            if (select) {
                select.add(new Option(result.name, result.id, true, true));
                select.value = String(result.id);
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
            modal.hide();
            notify(result.message ?? 'Tersimpan.');
        } catch (error) {
            let shown = false;
            Object.entries(error.errors ?? {}).forEach(([field, messages]) => {
                const target = form.querySelector(`[data-error-for="${field}"]`);
                if (target) {
                    target.textContent = [messages].flat()[0];
                    form.querySelector(`[name="${field}"]`)?.classList.add('is-invalid');
                    shown = true;
                }
            });
            if (!shown) { general.textContent = error.message; general.classList.remove('d-none'); }
        } finally {
            submit.disabled = false;
        }
    });
}

// U9 one-screen Add task: the project select refreshes PIC and assignee choices and the address the form posts to.
export function initTaskCreate() {
    const form = document.querySelector('[data-task-create]');
    if (!form) return;
    const members = JSON.parse(form.dataset.members || '{}');
    const project = form.querySelector('[name=project_id]');
    const pic = form.querySelector('[name=pic_user_id]');
    const list = form.querySelector('[data-assignee-list]');
    const hint = form.querySelector('[data-assignee-hint]');
    const me = Number(form.dataset.me);
    const render = () => {
        const people = members[project.value] ?? [];
        form.action = project.value ? form.dataset.urlTemplate.replace('__ID__', project.value) : '';
        const keepPic = Number(pic.value);
        pic.replaceChildren(new Option(project.value ? 'Pilih PIC' : 'Pilih project dulu', ''));
        people.forEach((p) => pic.add(new Option(`${p.name} (${p.open ?? 0} task aktif)`, p.id)));
        const preferred = people.find((p) => p.id === keepPic) ?? people.find((p) => p.id === me);
        if (preferred) pic.value = String(preferred.id);
        const ticked = new Set([...list.querySelectorAll('input:checked')].map((i) => Number(i.value)));
        list.replaceChildren(...people.flatMap((p) => {
            const input = Object.assign(document.createElement('input'), { type: 'checkbox', className: 'btn-check', name: 'assignees[]', value: p.id, id: `ta${p.id}`, autocomplete: 'off', checked: ticked.has(p.id) });
            const label = Object.assign(document.createElement('label'), { className: 'btn btn-sm btn-outline-primary', htmlFor: `ta${p.id}`, textContent: `${p.name} ` });
            label.append(Object.assign(document.createElement('span'), { className: 'load-count', title: 'Task aktif yang sedang dipegang', textContent: String(p.open ?? 0) }));
            return [input, label];
        }));
        hint.textContent = !project.value ? 'Pilih project untuk melihat anggotanya.'
            : (people.length ? 'Angka di samping nama = task aktif yang sedang dipegang. Hanya anggota project ini yang bisa dipilih.' : 'Project ini belum punya anggota aktif. Atur tim project dulu.');
    };
    project.addEventListener('change', render);
    form.addEventListener('submit', (e) => {
        if (!project.value) {
            e.preventDefault();
            project.classList.add('is-invalid');
            project.focus();
            notify('Pilih project dulu.', 'error');
        }
    });
    if (project.value && !list.children.length) render();
}

// Filters apply as soon as a choice changes (U9: no separate "Saring" click); the button stays for browsers without JavaScript.
export function initAutoSubmit() {
    document.documentElement.classList.add('js');
    document.querySelectorAll('form[data-auto-submit]').forEach((form) => {
        form.addEventListener('change', (e) => { if (e.target.matches('select, input[type=checkbox], input[type=radio], input[type=date]')) form.requestSubmit(); });
    });
}

// I4 quick deadline choices under a date field: today, tomorrow, Friday, one week later (17.00, end of the working day).
export function initQuickDates() {
    const pad = (n) => String(n).padStart(2, '0');
    const value = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T17:00`;
    const at = (days) => { const d = new Date(); d.setDate(d.getDate() + days); return d; };
    const friday = () => { const d = new Date(); const add = (5 - d.getDay() + 7) % 7 || 7; d.setDate(d.getDate() + (d.getDay() === 5 && d.getHours() < 17 ? 0 : add)); return d; };
    document.querySelectorAll('[data-quick-dates]').forEach((wrapper) => {
        const input = wrapper.querySelector('input[type="datetime-local"], input[type="date"]');
        if (!input || wrapper.querySelector('.quick-dates')) return;
        const box = document.createElement('div');
        box.className = 'quick-dates';
        box.setAttribute('role', 'group');
        box.setAttribute('aria-label', 'Pilihan cepat tanggal');
        [['Hari ini', () => at(0)], ['Besok', () => at(1)], ['Jumat', friday], ['+1 minggu', () => at(7)]].forEach(([label, pick]) => {
            const b = Object.assign(document.createElement('button'), { type: 'button', className: 'quick-date', textContent: label });
            b.addEventListener('click', () => {
                const v = value(pick());
                input.value = input.type === 'date' ? v.slice(0, 10) : v;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                box.querySelectorAll('.quick-date').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
            });
            box.appendChild(b);
        });
        input.insertAdjacentElement('afterend', box);
    });
}

// U7 permission table: ticking add/edit/delete or a special action also ticks view; clearing view clears the row.
// Section counts, search, and an unsaved-changes note.
export function initRoleMatrix() {
    const form = document.querySelector('[data-role-matrix]');
    if (!form) return;
    const dirty = form.querySelector('[data-dirty-note]');
    const count = () => form.querySelectorAll('[data-matrix-section]').forEach((section) => {
        const boxes = section.querySelectorAll('input[type=checkbox]');
        const ticked = [...boxes].filter((b) => b.checked).length;
        section.querySelector('[data-section-count]').textContent = `(${ticked}/${boxes.length})`;
    });
    form.addEventListener('change', (e) => {
        const box = e.target;
        if (!(box instanceof HTMLInputElement) || box.type !== 'checkbox') return;
        const row = box.closest('[data-matrix-row]');
        const view = row?.querySelector('[data-col="view"] input');
        if (view && box !== view && box.checked) view.checked = true;
        if (view && box === view && !view.checked) row.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = false; });
        if (dirty) dirty.hidden = false;
        count();
    });
    // U7.1: a "Semua" button per row ticks or clears the whole row.
    if (!form.querySelector('input[type=checkbox]:disabled')) {
        form.querySelectorAll('[data-matrix-row]').forEach((row) => {
            const boxes = row.querySelectorAll('input[type=checkbox]');
            if (boxes.length < 2) return;
            const b = Object.assign(document.createElement('button'), { type: 'button', className: 'row-all', textContent: 'Semua' });
            b.setAttribute('aria-label', `Centang semua izin ${row.querySelector('th').textContent.trim()}`);
            b.addEventListener('click', () => {
                const tick = [...boxes].some((x) => !x.checked);
                boxes.forEach((x) => { x.checked = tick; });
                if (dirty) dirty.hidden = false;
                count();
            });
            row.querySelector('th').appendChild(b);
        });
    }
    const search = form.querySelector('[data-matrix-search]');
    search?.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        let shown = 0;
        form.querySelectorAll('[data-matrix-section]').forEach((section) => {
            let any = false;
            section.querySelectorAll('[data-matrix-row]').forEach((row) => {
                const match = !q || row.dataset.label.includes(q) || row.textContent.toLowerCase().includes(q);
                row.hidden = !match;
                any ||= match;
            });
            section.hidden = !any;
            shown += any ? 1 : 0;
        });
        form.querySelector('[data-matrix-empty]').hidden = shown > 0;
    });
    count();
}

// Sidebar groups (U2): folding is remembered per browser; the group of the current page always stays open.
export function initSidebar() {
    const key = 'ptm.nav.collapsed';
    let collapsed = [];
    try { collapsed = JSON.parse(localStorage.getItem(key) || '[]'); } catch { collapsed = []; }
    document.querySelectorAll('[data-nav-group]').forEach((group) => {
        const slug = group.dataset.navGroup;
        const button = group.querySelector('.side-heading');
        const apply = (fold) => { group.classList.toggle('is-collapsed', fold); button.setAttribute('aria-expanded', String(!fold)); };
        apply(collapsed.includes(slug) && !group.hasAttribute('data-nav-current'));
        button.addEventListener('click', () => {
            const fold = !group.classList.contains('is-collapsed');
            apply(fold);
            collapsed = fold ? [...new Set([...collapsed, slug])] : collapsed.filter((s) => s !== slug);
            try { localStorage.setItem(key, JSON.stringify(collapsed)); } catch { /* storage unavailable: folding works for this page only */ }
        });
    });
}

// Keyboard shortcut: "/" jumps to the search box (ignored while typing in a field).
export function initShortcuts() {
    document.addEventListener('keydown', (e) => {
        if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey) return;
        const t = e.target;
        if (t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))) return;
        const search = document.querySelector('[data-shortcut="/"]');
        if (!search) return;
        e.preventDefault();
        search.focus();
        search.select?.();
    });
}

// I14 safe forms: a submitted form cannot be sent twice (its submit buttons wait), and leaving a longer form with unsaved
// typing asks first. Forms that handle submission themselves (confirm dialog, quick add) prevent the default and are skipped.
export function initSafeForms() {
    let leaving = false;
    const dirty = new Set();
    const guarded = (form) => form.method === 'post' && !form.hasAttribute('data-no-guard')
        && form.querySelectorAll('input:not([type=hidden]):not([type=checkbox]):not([type=radio]), textarea, select').length >= 3;
    document.addEventListener('input', (e) => { const form = e.target.form; if (form && guarded(form)) dirty.add(form); });
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;
        leaving = true;
        dirty.delete(form);
        if (form.method !== 'post') return;
        // Disable after the browser has collected the values (a disabled button would drop its own name/value).
        setTimeout(() => form.querySelectorAll('button[type=submit], button:not([type])').forEach((b) => {
            b.disabled = true;
            b.setAttribute('aria-busy', 'true');
        }), 0);
    });
    window.addEventListener('pageshow', (e) => {
        // Back/forward cache: buttons become usable again.
        if (e.persisted) document.querySelectorAll('button[aria-busy="true"]').forEach((b) => { b.disabled = false; b.removeAttribute('aria-busy'); });
        leaving = false;
    });
    window.addEventListener('beforeunload', (e) => {
        if (leaving || ![...dirty].some((f) => document.body.contains(f))) return;
        e.preventDefault();
        e.returnValue = '';
    });
}

// I16 term explanations: Bootstrap popovers that open on click, tap, or keyboard focus (never hover only).
export function initHints(Popover) {
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => new Popover(el));
}

// I7 bell in the header: the "Perlu kamu kerjakan" list, fetched after the page is shown (no extra wait on every page).
export async function initTodoBell() {
    const bell = document.querySelector('[data-todo-bell]');
    if (!bell) return;
    const box = bell.querySelector('[data-todo-items]');
    const count = bell.querySelector('[data-todo-count]');
    const button = bell.querySelector('button');
    try {
        const r = await fetch(bell.dataset.url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const { items = [] } = await r.json();
        count.hidden = items.length === 0;
        count.textContent = items.length > 9 ? '9+' : String(items.length);
        button.setAttribute('aria-label', items.length ? `Perlu kamu kerjakan: ${items.length}` : 'Perlu kamu kerjakan: tidak ada');
        box.replaceChildren(...(items.length ? items.map((item) => {
            const a = Object.assign(document.createElement('a'), { className: `dropdown-item todo-menu-item tone-${item.tone}`, href: item.url });
            const text = document.createElement('span');
            text.className = 'min-w-0';
            text.append(Object.assign(document.createElement('span'), { className: 'd-block fw-semibold text-truncate', textContent: item.title }),
                Object.assign(document.createElement('span'), { className: 'd-block small muted text-truncate', textContent: [item.detail, item.when].filter(Boolean).join(' · ') }));
            a.append(text);
            return a;
        }) : [Object.assign(document.createElement('p'), { className: 'small muted px-2 py-1 mb-0', textContent: 'Semua beres. Tidak ada yang menunggumu.' })]));
    } catch {
        box.replaceChildren(Object.assign(document.createElement('p'), { className: 'small muted px-2 py-1 mb-0', textContent: 'Daftar belum bisa dimuat. Buka Dashboard untuk melihatnya.' }));
    }
}

// I8 first steps card: can be hidden for this browser.
export function initFirstSteps() {
    const card = document.querySelector('[data-first-steps]');
    if (!card) return;
    const key = 'ptm.firstSteps.hidden';
    try { if (localStorage.getItem(key) === '1') card.hidden = true; } catch { /* storage unavailable */ }
    card.querySelector('[data-first-steps-hide]')?.addEventListener('click', () => {
        card.hidden = true;
        try { localStorage.setItem(key, '1'); } catch { /* storage unavailable */ }
    });
}

// File picker with an Indonesian label: shows the chosen file name.
export function initFilePickers() {
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        const name = input.closest('.file-pick')?.querySelector('[data-file-name]');
        input.addEventListener('change', () => { if (name) name.textContent = input.files[0]?.name ?? 'Belum ada berkas dipilih'; });
    });
}

// Copy button next to a value (I12 first password).
export function initCopyButtons() {
    document.querySelectorAll('[data-copy-button]').forEach((button) => {
        button.addEventListener('click', async () => {
            const text = button.parentElement.querySelector('[data-copy-source]')?.textContent.trim() ?? '';
            try { await navigator.clipboard.writeText(text); notify('Disalin.'); } catch { notify('Salin manual: pilih teksnya lalu Ctrl+C.', 'error'); }
        });
    });
}

// Local demo mode: a "Coba sebagai" button fills the normal login form and submits it (authentication still runs).
export function initDemoLogin() {
    const form = document.querySelector('[data-login-form]');
    if (!form) return;
    document.querySelectorAll('[data-demo-email]').forEach((button) => {
        button.addEventListener('click', () => {
            form.querySelector('input[name=email]').value = button.dataset.demoEmail;
            form.querySelector('input[name=password]').value = button.dataset.demoPassword;
            button.disabled = true;
            form.requestSubmit();
        });
    });
}

// Scrollable table regions must be reachable by keyboard (axe scrollable-region-focusable).
export function fixAccessibility() {
    document.querySelectorAll('.table-responsive').forEach((el) => {
        if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '0');
        if (!el.hasAttribute('aria-label')) {
            const title = el.closest('.panel')?.querySelector('h2, .panel-title')?.textContent?.trim();
            el.setAttribute('aria-label', title ? `Tabel ${title}` : 'Tabel data');
        }
        el.setAttribute('role', 'region');
    });
}
