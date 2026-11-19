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

// U4: the code field fills itself while the name is typed (the exact unique code comes from the server) and can still be
// changed. Once the person types a code of their own it is left alone; emptying the field makes it automatic again.
// An existing record keeps its code. code_auto=1 tells the server to make the code unique again when it saves.
export function initCodePreview() {
    document.querySelectorAll('[data-code-from]').forEach((wrapper) => {
        const input = wrapper.querySelector('input');
        const source = input?.form?.querySelector(`[name="${wrapper.dataset.codeFrom}"]`);
        if (!input || !source) return;
        const flag = Object.assign(document.createElement('input'), { type: 'hidden', name: 'code_auto' });
        input.after(flag);
        const url = wrapper.dataset.codeUrl;
        let auto = false;
        let timer = null;
        let ticket = 0;
        const setAuto = (on) => { auto = on; flag.value = on ? '1' : '0'; };
        const fill = () => {
            if (!auto) return;
            input.value = codeFromName(source.value);
            clearTimeout(timer);
            if (!url || !source.value.trim()) return;
            const mine = ++ticket;
            timer = setTimeout(async () => {
                try {
                    const res = await fetch(`${url}&name=${encodeURIComponent(source.value)}`, { headers: { Accept: 'application/json' } });
                    const data = res.ok ? await res.json() : null;
                    if (auto && mine === ticket && data?.code) input.value = data.code;
                } catch { /* the code made in the browser stays */ }
            }, 300);
        };
        setAuto(input.value.trim() === '' || wrapper.dataset.codeAuto === '1');
        input.placeholder = 'Terisi otomatis dari nama';
        input.addEventListener('input', () => { setAuto(input.value.trim() === ''); fill(); });
        source.addEventListener('input', fill);
        fill();
    });
}

// Simple formatting for a description (bold, italic, underline, lists): the editor shows the text as it will look and
// writes its HTML back into the textarea, which stays the real form field. The server keeps only the RichText allowlist.
export function initRichText(root = document) {
    root.querySelectorAll('[data-rich-text]').forEach((field) => {
        const area = field.querySelector('[data-rich-source]');
        const box = field.querySelector('[data-rich-editor]');
        const editor = box?.querySelector('[contenteditable]');
        if (!area || !editor || field.dataset.richReady) return;
        field.dataset.richReady = '1';
        editor.innerHTML = area.dataset.richHtml ?? '';
        box.hidden = false;
        area.hidden = true;
        field.querySelector('label')?.addEventListener('click', (e) => { e.preventDefault(); editor.focus(); });
        const tools = [...box.querySelectorAll('[data-command]')];
        const sync = (changed = true) => {
            const empty = editor.textContent.trim() === '' && !editor.querySelector('li');
            area.value = empty ? '' : editor.innerHTML;
            editor.classList.toggle('is-empty', empty);
            if (changed) area.dispatchEvent(new Event('input', { bubbles: true })); // the unsaved-changes guard sees the edit
        };
        const state = () => tools.forEach((b) => {
            if (!b.hasAttribute('aria-pressed')) return;
            let on = false;
            try { on = editor.contains(document.getSelection()?.anchorNode ?? null) && document.queryCommandState(b.dataset.command); } catch { /* not supported */ }
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        tools.forEach((b) => {
            b.addEventListener('mousedown', (e) => e.preventDefault()); // keep the selection in the text
            b.addEventListener('click', () => {
                editor.focus();
                document.execCommand(b.dataset.command, false);
                sync();
                state();
            });
        });
        // Pasted text arrives without the colours and fonts of the page it came from.
        editor.addEventListener('paste', (e) => {
            e.preventDefault();
            document.execCommand('insertText', false, e.clipboardData?.getData('text/plain') ?? '');
        });
        editor.addEventListener('input', () => sync());
        editor.addEventListener('keyup', state);
        editor.addEventListener('mouseup', state);
        area.form?.addEventListener('submit', () => sync(false));
        sync(false);
    });
}

// Dashboard: each person chooses which parts they see (a checklist), remembered in this browser per account.
export function initDashboardWidgets() {
    const page = document.querySelector('[data-dashboard]');
    const menu = document.querySelector('[data-widget-menu]');
    if (!page || !menu) return;
    const key = `ptm.dashboard.hidden.${page.dataset.dashboard}`;
    const widgets = [...page.querySelectorAll('[data-widget]')];
    let hidden = [];
    try {
        hidden = JSON.parse(localStorage.getItem(key) ?? '[]');
        if (localStorage.getItem('ptm.firstSteps.hidden') === '1') hidden.push('first-steps'); // hidden earlier with its own button
    } catch { hidden = []; }
    if (!Array.isArray(hidden)) hidden = [];
    const empty = page.querySelector('[data-widgets-empty]');
    const apply = () => {
        widgets.forEach((w) => { w.hidden = hidden.includes(w.dataset.widget); });
        // A part whose neighbour in the same row is hidden takes the full width.
        page.querySelectorAll('[data-widget-row]').forEach((row) => {
            const visible = [...row.querySelectorAll(':scope > [data-widget]')].filter((w) => !w.hidden);
            row.hidden = visible.length === 0;
            visible.forEach((w) => w.classList.toggle('widget-full', visible.length === 1));
        });
        if (empty) empty.hidden = widgets.some((w) => !w.hidden);
        menu.querySelectorAll('input[data-widget-toggle]').forEach((box) => { box.checked = !hidden.includes(box.value); });
    };
    menu.querySelectorAll('input[data-widget-toggle]').forEach((box) => {
        if (!widgets.some((w) => w.dataset.widget === box.value)) box.closest('li')?.remove();
        box.addEventListener('change', () => {
            hidden = box.checked ? hidden.filter((h) => h !== box.value) : [...new Set([...hidden, box.value])];
            try {
                localStorage.setItem(key, JSON.stringify(hidden));
                if (box.value === 'first-steps') localStorage.removeItem('ptm.firstSteps.hidden');
            } catch { /* storage unavailable */ }
            apply();
        });
    });
    menu.querySelector('[data-widgets-all]')?.addEventListener('click', () => {
        hidden = [];
        try { localStorage.removeItem(key); localStorage.removeItem('ptm.firstSteps.hidden'); } catch { /* storage unavailable */ }
        apply();
    });
    apply();
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
export function initQuickDates(root = document) {
    const pad = (n) => String(n).padStart(2, '0');
    const value = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T17:00`;
    const at = (days) => { const d = new Date(); d.setDate(d.getDate() + days); return d; };
    const friday = () => { const d = new Date(); const add = (5 - d.getDay() + 7) % 7 || 7; d.setDate(d.getDate() + (d.getDay() === 5 && d.getHours() < 17 ? 0 : add)); return d; };
    root.querySelectorAll('[data-quick-dates]').forEach((wrapper) => {
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
export function initHints(Popover, root = document) {
    root.querySelectorAll('[data-bs-toggle="popover"]').forEach((el) => Popover.getOrCreateInstance(el));
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
            if (item.panel) a.dataset.taskPanel = ''; // I3: a task opens in the side panel
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
    // On the Dashboard the card is one of the parts in the "Atur tampilan" checklist, so hiding goes through that list.
    const toggle = () => document.querySelector('[data-widget-menu] input[data-widget-toggle][value="first-steps"]');
    if (card.dataset.widget && document.querySelector('[data-widget-menu]')) {
        card.querySelector('[data-first-steps-hide]')?.addEventListener('click', () => { const box = toggle(); if (box?.checked) box.click(); });
        return;
    }
    try { if (localStorage.getItem(key) === '1') card.hidden = true; } catch { /* storage unavailable */ }
    card.querySelector('[data-first-steps-hide]')?.addEventListener('click', () => {
        card.hidden = true;
        try { localStorage.setItem(key, '1'); } catch { /* storage unavailable */ }
    });
}

// File picker with an Indonesian label: shows the chosen file name.
export function initFilePickers(root = document) {
    root.querySelectorAll('[data-file-input]').forEach((input) => {
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

// I3: after the side panel changed a task, the page behind refreshes in place. Regions marked data-refresh-region are
// replaced from a fresh copy of this page (scroll positions of data-keep-scroll parts are kept); DataTables reload
// their current page on the ptm:tasks-changed event. If the refresh fails the page simply stays as it was.
export async function refreshRegions() {
    const regions = [...document.querySelectorAll('[data-refresh-region]')];
    if (!regions.length) return;
    try {
        const r = await fetch(window.location.href, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        if (!r.ok) return;
        const doc = new DOMParser().parseFromString(await r.text(), 'text/html');
        regions.forEach((old) => {
            const fresh = doc.querySelector(`[data-refresh-region="${CSS.escape(old.dataset.refreshRegion)}"]`);
            if (!fresh || !document.body.contains(old)) return;
            const kept = new Map([...old.querySelectorAll('[data-keep-scroll]')].map((x) => [x.dataset.keepScroll, [x.scrollLeft, x.scrollTop]]));
            const [left, top] = [old.scrollLeft, old.scrollTop];
            old.replaceWith(fresh);
            fresh.scrollLeft = left;
            fresh.scrollTop = top;
            fresh.querySelectorAll('[data-keep-scroll]').forEach((x) => {
                const at = kept.get(x.dataset.keepScroll);
                if (at) [x.scrollLeft, x.scrollTop] = at;
            });
            document.dispatchEvent(new CustomEvent('ptm:region-replaced', { detail: fresh }));
        });
    } catch {
        // Network trouble: the page keeps what it showed; a reload shows the latest.
    }
}

// I3 task detail as a side panel. Links marked data-task-panel open the task over the board, list, or dashboard, so the
// column, filters, and scroll position stay. Ctrl/Cmd/Shift or middle click still opens the full page. Forms inside are
// sent to the same endpoints as JSON (same permission and status rules) and the panel reloads with the result.
// On phones the panel fills the screen and the Back button closes it.
export function initTaskPanel(Offcanvas, { onContent } = {}) {
    const el = document.getElementById('task-panel');
    if (!el) return;
    const panel = Offcanvas.getOrCreateInstance(el);
    const body = el.querySelector('[data-task-panel-body]');
    const title = el.querySelector('#task-panel-title');
    const project = el.querySelector('[data-task-panel-project]');
    const full = el.querySelector('[data-task-panel-full]');
    let current = '';
    let trigger = null;
    let triggerHref = '';
    let changed = false;
    let pushed = false;

    const message = (text) => {
        const box = Object.assign(document.createElement('div'), { className: 'empty-state py-4' });
        box.append(Object.assign(document.createElement('strong'), { textContent: text }),
            Object.assign(document.createElement('a'), { href: current || '#', textContent: 'Buka halaman task' }));
        body.replaceChildren(box);
    };
    const skeleton = () => {
        const box = Object.assign(document.createElement('div'), { className: 'placeholder-glow task-panel-loading' });
        box.setAttribute('aria-hidden', 'true');
        ['col-5', 'col-12', 'col-9', 'col-12', 'col-7', 'col-10'].forEach((w) => box.append(Object.assign(document.createElement('span'), { className: `placeholder ${w}` })));
        const status = Object.assign(document.createElement('p'), { className: 'visually-hidden', textContent: 'Memuat task...' });
        status.setAttribute('role', 'status');
        body.replaceChildren(status, box);
    };
    const jumpTo = (hash) => {
        if (!hash || hash === '#') return;
        const target = body.querySelector(`#${CSS.escape(hash.slice(1))}`);
        if (!target) return;
        target.scrollIntoView({ block: 'start' });
        target.querySelector('input:not([type=hidden]):not([type=checkbox]), textarea')?.focus({ preventScroll: true });
    };

    async function load(url, hash = '') {
        current = url;
        full.href = url + hash;
        const address = new URL(url, window.location.href);
        address.searchParams.set('panel', '1');
        try {
            const r = await fetch(address, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (r.status === 403) throw new Error('Kamu tidak punya akses ke task ini.');
            if (r.status === 404) throw new Error('Task ini sudah tidak ada.');
            if (!r.ok) throw new Error('Task belum bisa dimuat. Coba lagi sebentar.');
            const content = new DOMParser().parseFromString(await r.text(), 'text/html').querySelector('[data-task-panel-content]');
            if (!content) throw new Error('Sesimu sudah berakhir. Muat ulang halaman untuk masuk lagi.');
            if (current !== url) return; // another task was opened meanwhile
            body.replaceChildren(content);
            // The button that was pressed is gone after a reload: keep focus inside the panel so Esc and Tab still work there.
            if (!el.contains(document.activeElement) || document.activeElement === document.body) el.focus({ preventScroll: true });
            title.textContent = content.dataset.title;
            project.textContent = content.dataset.project ?? '';
            onContent?.(content);
            jumpTo(hash);
        } catch (error) {
            if (current === url) message(error.message);
        }
    }

    const showErrors = (form, data, status) => {
        form.querySelectorAll('.is-invalid').forEach((x) => x.classList.remove('is-invalid'));
        form.querySelectorAll('[data-panel-error]').forEach((x) => x.remove());
        let first = null;
        Object.entries(data.errors ?? {}).forEach(([name, texts]) => {
            const field = form.querySelector(`[name="${CSS.escape(name)}"], [name="${CSS.escape(name)}[]"]`)
                ?? (name === 'status' || name === 'decision' ? form.querySelector('[type=submit]') : null);
            const text = [texts].flat()[0];
            if (!field) { first ??= { text }; return; }
            if (field.type !== 'submit' && field.type !== 'hidden') field.classList.add('is-invalid');
            first ??= { text, field };
            // One rule can name two fields (link or file): the sentence is shown once.
            if ([...form.querySelectorAll('[data-panel-error]')].some((x) => x.textContent === text)) return;
            const note = Object.assign(document.createElement('div'), { className: 'invalid-feedback d-block', textContent: text });
            note.dataset.panelError = '';
            (field.closest('.file-pick, .form-check') ?? field).insertAdjacentElement('afterend', note);
        });
        const fallback = status === 403 ? 'Kamu tidak punya izin untuk aksi ini.'
            : status === 419 ? 'Halaman sudah lama terbuka. Muat ulang, lalu coba lagi.'
                : (data.message && status < 500 ? data.message : 'Perubahan tidak tersimpan. Coba lagi.');
        notify(first?.text ?? fallback, 'error');
        first?.field?.focus?.();
    };

    let closing = false;
    el.addEventListener('hide.bs.offcanvas', () => { closing = true; });
    document.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-task-panel]');
        if (!link || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        openTask(link.href, link.textContent.trim(), link);
    });
    // Scripts that draw their own task links (calendar, command palette) ask for the panel with this event.
    document.addEventListener('ptm:open-task', (e) => openTask(e.detail.url, e.detail.title ?? ''));
    function openTask(href, label = '', link = null) {
        // A task picked while the panel is still closing opens once it has closed (the close would otherwise clear it).
        if (closing) { el.addEventListener('hidden.bs.offcanvas', () => openTask(href, label, link), { once: true }); return; }
        const address = new URL(href, window.location.href);
        const hash = address.hash;
        address.hash = '';
        trigger = link ?? document.activeElement;
        triggerHref = link?.getAttribute('href') ?? '';
        skeleton();
        title.textContent = label || 'Detail task';
        project.textContent = '';
        if (!el.classList.contains('show')) panel.show();
        load(address.toString(), hash);
    }

    // Links to a part of the same task (#submit-result, #review) scroll inside the panel instead of adding history entries.
    body.addEventListener('click', (e) => {
        const link = e.target.closest('a[href^="#"]');
        if (!link) return;
        e.preventDefault();
        jumpTo(link.getAttribute('href'));
    });

    body.addEventListener('change', (e) => { if (e.target.matches?.('[data-check-subtask]')) changed = true; });

    body.addEventListener('submit', async (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.method !== 'post') return;
        if (form.dataset.confirm && form.dataset.confirmed !== '1') return; // the confirmation dialog asks first, then submits again
        e.preventDefault();
        delete form.dataset.confirmed;
        const data = new FormData(form, e.submitter ?? undefined);
        const buttons = [...form.querySelectorAll('button[type=submit], button:not([type])')];
        if (form.contains(document.activeElement)) el.focus({ preventScroll: true }); // a disabled button would drop focus to the page
        buttons.forEach((b) => { b.disabled = true; b.setAttribute('aria-busy', 'true'); });
        try {
            const r = await fetch(form.action, {
                method: 'POST', body: data, credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken() },
            });
            let result = {};
            try { result = await r.json(); } catch { result = {}; }
            if (!r.ok) { showErrors(form, result, r.status); return; }
            changed = true;
            document.querySelectorAll('.toast-area .alert-danger').forEach((x) => x.remove()); // an earlier error no longer applies
            notify(result.message ?? 'Tersimpan.');
            if (result.deleted) { panel.hide(); return; }
            const statusBefore = body.querySelector('[data-task-panel-content]')?.dataset.status;
            body.setAttribute('aria-busy', 'true'); // the saved result is on its way: the old content is dimmed until it arrives
            await load(current);
            body.removeAttribute('aria-busy');
            // A new status (started, sent for review, accepted) is shown from the top, where the status and next step are.
            if (body.querySelector('[data-task-panel-content]')?.dataset.status !== statusBefore) body.scrollTop = 0;
        } catch {
            notify('Perubahan tidak tersimpan. Periksa koneksi, lalu coba lagi.', 'error');
        } finally {
            body.removeAttribute('aria-busy');
            buttons.forEach((b) => { b.disabled = false; b.removeAttribute('aria-busy'); });
        }
    });

    el.addEventListener('shown.bs.offcanvas', () => {
        if (!pushed) {
            window.history.pushState({ ...(window.history.state ?? {}), taskPanel: true }, '');
            pushed = true;
        }
    });
    // Esc closes the panel wherever focus is (a dialog on top, such as the confirmation, closes first).
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && el.classList.contains('show') && !document.querySelector('.modal.show')) panel.hide();
    });
    let ownBack = false; // the Back step taken by closing the panel ourselves must not close a panel opened right after
    window.addEventListener('popstate', () => {
        if (ownBack) { ownBack = false; return; }
        if (!pushed) return;
        pushed = false;
        panel.hide();
    });
    el.addEventListener('hidden.bs.offcanvas', async () => {
        closing = false;
        body.replaceChildren();
        current = '';
        const [lastTrigger, lastHref] = [trigger, triggerHref]; // another task may be opened while the page refreshes
        if (pushed) { pushed = false; ownBack = true; window.history.back(); }
        if (!changed) { lastTrigger?.focus({ preventScroll: true }); return; }
        changed = false;
        document.dispatchEvent(new CustomEvent('ptm:tasks-changed'));
        await refreshRegions();
        if (el.classList.contains('show')) return;
        const again = lastHref && [...document.querySelectorAll('a[data-task-panel]')].find((a) => a.getAttribute('href') === lastHref);
        again?.focus({ preventScroll: true });
    });
}

// I5 long people lists (pills or check boxes): a "Cari nama" box narrows the list while typing. Ticked people always stay
// visible, so the filter never hides a choice that will be saved. Lists shorter than 8 people get no box. The box belongs
// to no form, so typing in it never submits or marks the form as changed.
export function initPeopleFilter(root = document) {
    root.querySelectorAll('[data-people-filter]').forEach((list) => {
        if (list.dataset.peopleFilterReady) return;
        list.dataset.peopleFilterReady = '1';
        const itemSelector = list.dataset.peopleFilter || 'label';
        const id = `people-filter-${Math.random().toString(36).slice(2, 8)}`;
        const box = Object.assign(document.createElement('div'), { className: 'people-filter' });
        const label = Object.assign(document.createElement('label'), { className: 'visually-hidden', htmlFor: id, textContent: 'Cari nama' });
        const input = Object.assign(document.createElement('input'), { type: 'search', id, className: 'form-control form-control-sm', placeholder: 'Cari nama', autocomplete: 'off' });
        input.setAttribute('form', `${id}-none`);
        const count = Object.assign(document.createElement('span'), { className: 'small muted' });
        count.setAttribute('aria-live', 'polite');
        box.append(label, input, count);
        list.before(box);
        const checkbox = (item) => (item.matches('label') ? document.getElementById(item.htmlFor) : item.querySelector('input[type=checkbox]'));
        const apply = () => {
            const q = input.value.trim().toLowerCase();
            let total = 0;
            let shown = 0;
            list.querySelectorAll(itemSelector).forEach((item) => {
                if (item.closest('[hidden]')) return; // hidden by another rule, such as people of another company
                total += 1;
                const match = !q || item.textContent.toLowerCase().includes(q) || checkbox(item)?.checked;
                item.toggleAttribute('data-filtered', !match);
                shown += match ? 1 : 0;
            });
            box.hidden = total < 8 && !q;
            count.textContent = q ? `${shown} dari ${total}` : '';
        };
        input.addEventListener('input', apply);
        input.addEventListener('keydown', (e) => { if (e.key === 'Enter') e.preventDefault(); });
        // Lists that are rebuilt (Tambah task after choosing a project) or narrowed by company are filtered again.
        new MutationObserver(apply).observe(list, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
        apply();
    });
}

// PIC and assignees form: the new PIC is ticked as an assignee at once; on a task still in the queue the previous PIC is unticked
// (handed over completely), on a started task they stay (same rule as the bulk "Ganti PIC"). One listener for pages and the side panel.
let picHandoverReady = false;
export function initPicHandover() {
    if (picHandoverReady) return;
    picHandoverReady = true;
    document.addEventListener('focusin', (e) => {
        const select = e.target;
        if (select instanceof HTMLSelectElement && select.name === 'pic_user_id' && select.closest('form[data-pic-handover]')) select.dataset.previous = select.value;
    });
    document.addEventListener('change', (e) => {
        const select = e.target;
        const form = select instanceof HTMLSelectElement && select.name === 'pic_user_id' ? select.closest('form[data-pic-handover]') : null;
        if (!form) return;
        const box = (id) => form.querySelector(`input[name="assignees[]"][value="${CSS.escape(id ?? '')}"]`);
        const fresh = box(select.value);
        if (fresh) fresh.checked = true;
        // The PIC before this change: remembered on focus, else the saved PIC the form was drawn with.
        const previous = box(select.dataset.previous || form.dataset.pic);
        if (previous && previous !== fresh && form.dataset.status === 'backlog') previous.checked = false;
        select.dataset.previous = select.value;
    });
}

// A number field that has focus changes its value when the mouse wheel turns over it (Chrome, Edge): scrolling the page with the
// pointer over "Deadline tgl" silently turned 28 into 10. The field gives up focus first, so the wheel only scrolls the page.
let numberWheelReady = false;
export function initNumberWheelGuard() {
    if (numberWheelReady) return;
    numberWheelReady = true;
    document.addEventListener('wheel', (e) => {
        const field = e.target;
        if (field instanceof HTMLInputElement && field.type === 'number' && document.activeElement === field) field.blur();
    }, { passive: true, capture: true });
}
