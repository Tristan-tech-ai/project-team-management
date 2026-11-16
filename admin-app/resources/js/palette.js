// I9 command palette: Ctrl+K (Cmd+K) opens it anywhere. Menu items and create actions are filtered locally right away;
// projects, tasks, and members come from GET /search (JSON, same data scope as the search page) after a short pause.
// Arrow keys choose, Enter opens; a task opens in the side panel (I3). "?" shows the keyboard shortcuts, "n" starts a new task.
const SHORTCUTS = [
    ['Ctrl K', 'Buka perintah cepat ini'],
    ['/', 'Ke kotak cari di atas'],
    ['N', 'Tambah task baru'],
    ['?', 'Daftar pintasan ini'],
    ['Esc', 'Tutup jendela atau panel'],
];

const typing = (t) => t instanceof HTMLElement && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName));

export function initPalette(Modal) {
    const el = document.querySelector('[data-command-palette]');
    if (!el) return;
    const modal = Modal.getOrCreateInstance(el);
    const input = el.querySelector('#palette-input');
    const list = el.querySelector('#palette-list');
    const status = el.querySelector('[data-palette-status]');
    const icons = el.querySelector('[data-palette-icons]').content;
    let actions = [];
    try { actions = JSON.parse(el.dataset.actions || '[]'); } catch { actions = []; }
    const newTask = el.dataset.newTask;
    let items = [];
    let active = -1;
    let timer = 0;
    let seq = 0;
    let help = false;

    const icon = (name) => {
        const holder = icons.querySelector(`[data-icon="${CSS.escape(name)}"]`) ?? icons.querySelector('[data-icon="search"]');
        const span = document.createElement('span');
        span.className = 'palette-icon';
        span.setAttribute('aria-hidden', 'true');
        if (holder?.firstElementChild) span.append(holder.firstElementChild.cloneNode(true));
        return span;
    };

    const select = (i) => {
        if (!items.length) { active = -1; input.removeAttribute('aria-activedescendant'); return; }
        active = (i + items.length) % items.length;
        items.forEach(({ li }, n) => li.setAttribute('aria-selected', String(n === active)));
        input.setAttribute('aria-activedescendant', items[active].li.id);
        items[active].li.scrollIntoView({ block: 'nearest' });
    };

    const render = (groups, query) => {
        list.replaceChildren();
        items = [];
        groups.filter((g) => g.rows.length).forEach((g) => {
            const head = Object.assign(document.createElement('li'), { className: 'palette-group', textContent: g.title });
            head.setAttribute('role', 'presentation');
            list.append(head);
            g.rows.forEach((row) => {
                const index = items.length;
                const li = Object.assign(document.createElement('li'), { className: 'palette-item', id: `palette-option-${index}` });
                li.setAttribute('role', 'option');
                const text = Object.assign(document.createElement('span'), { className: 'palette-text' });
                text.append(Object.assign(document.createElement('span'), { className: 'palette-label', textContent: row.label }));
                if (row.detail) text.append(Object.assign(document.createElement('span'), { className: 'palette-detail', textContent: row.detail }));
                li.append(row.key ? Object.assign(document.createElement('kbd'), { className: 'kbd palette-key', textContent: row.key }) : icon(row.icon ?? g.icon ?? 'search'), text);
                if (!row.url) li.setAttribute('aria-disabled', 'true');
                li.addEventListener('mousemove', () => { if (active !== index) select(index); });
                li.addEventListener('click', () => go(index));
                items.push({ li, row });
                list.append(li);
            });
        });
        select(0);
        // The count is for screen readers; a sentence is shown only when nothing matched.
        status.classList.toggle('visually-hidden', items.length > 0);
        status.textContent = items.length ? `${items.length} pilihan` : (query ? 'Tidak ada yang cocok. Coba kata lain, atau tekan Enter untuk halaman pencarian.' : '');
    };

    const matchActions = (query) => {
        const words = query.toLowerCase().split(/\s+/).filter(Boolean);
        return actions.filter((a) => words.every((w) => `${a.label} ${a.detail}`.toLowerCase().includes(w)));
    };

    const defaults = () => [{ title: 'Buat', rows: actions.filter((a) => a.group === 'Buat') }, { title: 'Buka', rows: actions.filter((a) => a.group === 'Buka') }];
    const localGroups = (query) => {
        if (help) return [{ title: 'Pintasan keyboard', rows: SHORTCUTS.filter(([k]) => k !== 'N' || newTask).map(([key, label]) => ({ key, label })) }, ...defaults()];
        return query ? [{ title: 'Menu dan aksi', rows: matchActions(query).slice(0, 8) }] : defaults();
    };

    async function search(query, mine) {
        try {
            const url = new URL(el.dataset.searchUrl, window.location.href);
            url.searchParams.set('keyword', query);
            const r = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
            if (!r.ok || mine !== seq) return;
            const data = await r.json();
            if (mine !== seq) return;
            const keep = items[active]?.row;
            render([...localGroups(query),
                { title: 'Project', icon: 'folder-kanban', rows: data.projects ?? [] },
                { title: 'Task', icon: 'list-todo', rows: data.tasks ?? [] },
                { title: 'Anggota', icon: 'users', rows: data.members ?? [] }], query);
            const again = items.findIndex(({ row }) => row === keep || (keep && row.url === keep.url));
            if (again > 0) select(again);
        } catch {
            if (mine === seq) status.textContent = 'Pencarian belum bisa dimuat. Tekan Enter untuk membuka halaman pencarian.';
        }
    }

    const update = () => {
        const query = input.value.trim();
        if (query) help = false;
        seq += 1;
        clearTimeout(timer);
        render(localGroups(query), query);
        if (query.length >= 2) {
            const mine = seq;
            status.classList.remove('visually-hidden');
            status.textContent = 'Mencari...';
            timer = setTimeout(() => search(query, mine), 200);
        }
    };

    function go(index) {
        const target = items[index]?.row;
        const query = input.value.trim();
        if (!target) {
            // Nothing matched: the full search page still helps (for example a code or an email).
            if (query.length >= 2) window.location.assign(`${el.dataset.searchUrl}?keyword=${encodeURIComponent(query)}`);
            return;
        }
        if (!target.url) return;
        if (target.panel) {
            // Open the task in the side panel once the palette is gone, so the two dialogs never fight over focus.
            el.addEventListener('hidden.bs.modal', () => {
                document.dispatchEvent(new CustomEvent('ptm:open-task', { detail: { url: target.url, title: target.label } }));
            }, { once: true });
            modal.hide();
            return;
        }
        window.location.assign(target.url);
    }

    input.addEventListener('input', update);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); select(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); select(active - 1); }
        else if (e.key === 'Home' && items.length && !input.value) { e.preventDefault(); select(0); }
        else if (e.key === 'End' && items.length && !input.value) { e.preventDefault(); select(items.length - 1); }
        else if (e.key === 'Enter') { e.preventDefault(); go(active); }
    });
    el.addEventListener('shown.bs.modal', () => { input.focus(); input.select(); });
    let returnFocus = null;
    el.addEventListener('hidden.bs.modal', () => {
        input.value = '';
        help = false;
        seq += 1;
        // Focus goes back to where it was; otherwise it would stay in the hidden search box and block the shortcuts.
        if (el.contains(document.activeElement)) {
            if (returnFocus && document.body.contains(returnFocus) && returnFocus !== document.body) returnFocus.focus({ preventScroll: true });
            else document.activeElement.blur();
        }
    });

    const open = (withHelp = false) => {
        returnFocus = document.activeElement;
        help = withHelp;
        input.value = '';
        update();
        modal.show();
    };
    document.querySelectorAll('[data-open-palette]').forEach((b) => b.addEventListener('click', () => open(b.dataset.openPalette === 'help')));

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && !e.shiftKey && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            if (el.classList.contains('show')) modal.hide(); else open();
            return;
        }
        if (e.ctrlKey || e.metaKey || e.altKey || typing(e.target) || document.querySelector('.modal.show, .offcanvas.show')) return;
        if (e.key === '?') { e.preventDefault(); open(true); }
        else if ((e.key === 'n' || e.key === 'N') && newTask) { e.preventDefault(); window.location.assign(newTask); }
    });
}
