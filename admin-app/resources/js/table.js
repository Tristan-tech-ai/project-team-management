// Yajra DataTables server-side (Technical Guide 9.3). Column definitions live in data-table-columns, never data-columns:
// DataTables 2 reads data-* attributes named after its own options and would replace the render functions below with plain strings.
// All text is rendered through DataTable.render.text(), so user
// content never becomes HTML; action buttons are built from URLs sent by the server.
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';
import { sendJson, notify } from './core';

window.jQuery = window.$ = $;

const language = {
    processing: 'Memuat...',
    search: 'Cari:',
    lengthMenu: 'Tampilkan _MENU_ baris',
    info: '_START_ sampai _END_ dari _TOTAL_ baris',
    infoEmpty: 'Belum ada data',
    infoFiltered: '(disaring dari _MAX_ baris)',
    zeroRecords: 'Tidak ada yang cocok dengan pencarian ini.',
    emptyTable: 'Belum ada data. Tambahkan yang pertama.',
    paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
    aria: { paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' } },
};

function pill(text, cssClass) {
    const s = document.createElement('span');
    s.className = `pill pill-${cssClass}`;
    s.textContent = text;
    return s.outerHTML;
}

function escape(t) {
    const d = document.createElement('div');
    d.textContent = t ?? '';
    return d.innerHTML;
}

const renderers = {
    text: DataTable.render.text(),
    // A name that opens its record (row.link from the server); people click names more often than a button at the row end.
    link: (data, type, row) => (type === 'display' && row.link ? `<a class="fw-semibold" href="${escape(row.link)}"${row.link_panel ? ' data-task-panel' : ''}>${escape(data)}</a>` : escape(data)),
    // I13: tick box for bulk actions; the server sends the task id only when this user may change the task.
    select: (data, type, row) => (type === 'display' && data
        ? `<label class="bulk-tick"><input class="form-check-input" type="checkbox" data-bulk-row value="${Number(data)}" aria-label="Pilih ${escape(row.title)}"></label>`
        : ''),
    badge: (data, type, row, meta) => {
        if (type !== 'display') return data;
        const column = meta.settings.aoColumns[meta.col].data;
        return pill(data ?? '-', row[`${column}_class`] ?? 'neutral');
    },
    progress: (data, type) => (type === 'display'
        ? `<div class="d-flex align-items-center gap-2"><div class="meter flex-grow-1" style="min-width:80px"><span style="width:${Number(data) || 0}%"></span></div><span class="numeric small">${Number(data) || 0}%</span></div>`
        : data),
    actions: (data, type, row) => {
        if (type !== 'display') return '';
        const buttons = (row.actions ?? []).map((a) => {
            if (a.method) {
                return `<form method="post" action="${escape(a.url)}" class="d-inline" ${a.confirmation ? `data-confirm="${escape(a.confirmation)}"` : ''}>
                    <input type="hidden" name="_token" value="${escape(document.querySelector('meta[name="csrf-token"]').content)}">
                    <input type="hidden" name="_method" value="${escape(a.method)}">
                    ${Object.entries(a.fields ?? {}).map(([k, v]) => `<input type="hidden" name="${escape(k)}" value="${escape(v)}">`).join('')}
                    <button type="submit" class="btn btn-sm ${a.fields ? 'btn-primary' : 'btn-outline-secondary'}">${escape(a.label)}</button></form>`;
            }
            // I3: a task link opens the side panel (the list keeps its page, search, and filters).
            return `<a href="${escape(a.url)}" class="btn btn-sm ${a.primary ? 'btn-primary' : 'btn-outline-primary'}"${a.panel ? ' data-task-panel' : ''}>${escape(a.label)}</a>`;
        });
        return `<div class="d-flex gap-1 justify-content-end flex-wrap">${buttons.join('')}</div>`;
    },
};

export function initTables() {
    document.querySelectorAll('table[data-table]').forEach((el) => {
        const columns = JSON.parse(el.dataset.tableColumns).map((c) => ({
            data: c.data,
            name: c.name ?? c.data,
            orderable: c.orderable ?? true,
            searchable: c.searchable ?? true,
            className: c.cssClass ?? '',
            render: renderers[c.render ?? 'text'],
        }));
        const filter = el.dataset.filter ? document.querySelector(el.dataset.filter) : null;
        // I6: filter choices live in the page address, so coming back from a detail page or sharing the link keeps them.
        if (filter) {
            const params = new URLSearchParams(window.location.search);
            filter.querySelectorAll('select[name], input[name]').forEach((field) => {
                if (params.has(field.name)) field.value = params.get(field.name);
            });
        }
        // One search box per page: when the page filter has its own "Cari judul", the table's built-in search box is not shown
        // (and a search remembered from before is cleared, so no hidden filter is left).
        const keyword = filter?.querySelector('[name="keyword"]');
        const pageLength = Number(document.body.dataset.tablePageLength);
        // I15: on phones every row becomes a card; each cell carries its column title as a label.
        const headers = [...el.querySelectorAll('thead th')].map((th) => th.textContent.trim());
        el.classList.add('table-cards');
        const table = new DataTable(el, {
            createdRow: (row) => row.querySelectorAll('td').forEach((td, i) => { if (headers[i]) td.dataset.label = headers[i]; }),
            processing: true,
            serverSide: true,
            ajax: {
                url: el.dataset.table,
                data: (d) => {
                    if (filter) new FormData(filter).forEach((v, k) => { d[k] = v; });
                },
            },
            columns,
            order: el.dataset.order ? JSON.parse(el.dataset.order) : [[0, 'asc']],
            pageLength: [10, 25, 50, 100].includes(pageLength) ? pageLength : 25,
            language,
            autoWidth: false,
            // I6: search text, sort, and page are remembered per table for this browser tab.
            stateSave: true,
            stateDuration: -1,
            ...(keyword ? { layout: { topEnd: null }, stateLoadParams: (s, data) => { if (data.search) data.search.search = ''; } } : {}),
        });
        const rememberFilters = () => {
            const url = new URL(window.location.href);
            new FormData(filter).forEach((v, k) => { if (v) url.searchParams.set(k, String(v)); else url.searchParams.delete(k); });
            window.history.replaceState(null, '', url);
        };
        filter?.addEventListener('change', () => { rememberFilters(); table.ajax.reload(); });
        // I3: after the side panel changed a task, reload the rows on the same page (paging and search stay).
        document.addEventListener('ptm:tasks-changed', () => table.ajax.reload(null, false));
        filter?.addEventListener('submit', (e) => { e.preventDefault(); rememberFilters(); table.ajax.reload(); });
        // The list follows the title search while typing (a short pause first, so the server is asked once per word).
        let typing = null;
        keyword?.addEventListener('input', () => { clearTimeout(typing); typing = setTimeout(() => { rememberFilters(); table.ajax.reload(); }, 350); });
        const bulk = el.id ? document.querySelector(`[data-bulk-form][data-table="#${CSS.escape(el.id)}"]`) : null;
        if (bulk) initBulk(el, table, bulk);
    });
}

// I13 bulk bar: the ticked task ids are kept while paging, searching, or filtering; the bar shows how many are ticked.
function initBulk(el, table, form) {
    const picked = new Set();
    const all = el.querySelector('[data-bulk-all]');
    const count = form.querySelector('[data-bulk-count]');
    const action = form.querySelector('[data-bulk-action]');
    const person = form.querySelector('[data-bulk-user]');
    const result = form.querySelector('[data-bulk-result]');
    const rows = () => [...el.querySelectorAll('tbody [data-bulk-row]')];
    const sync = () => {
        rows().forEach((box) => { box.checked = picked.has(Number(box.value)); });
        const onPage = rows();
        const ticked = onPage.filter((b) => b.checked).length;
        if (all) {
            all.checked = onPage.length > 0 && ticked === onPage.length;
            all.indeterminate = ticked > 0 && ticked < onPage.length;
            all.disabled = onPage.length === 0;
        }
        count.textContent = String(picked.size);
        form.hidden = picked.size === 0 && result.hidden;
    };
    const showPerson = () => {
        person.hidden = action.value === 'start';
        person.required = !person.hidden;
    };
    showPerson();
    action.addEventListener('change', showPerson);
    table.on('draw', sync);
    el.addEventListener('change', (e) => {
        const box = e.target;
        if (box.matches('[data-bulk-row]')) {
            if (box.checked) picked.add(Number(box.value)); else picked.delete(Number(box.value));
        } else if (box === all) {
            rows().forEach((b) => { if (all.checked) picked.add(Number(b.value)); else picked.delete(Number(b.value)); });
        } else {
            return;
        }
        result.hidden = true;
        sync();
    });
    form.querySelector('[data-bulk-page]')?.addEventListener('click', () => {
        rows().forEach((b) => picked.add(Number(b.value)));
        sync();
    });
    form.querySelector('[data-bulk-clear]').addEventListener('click', () => {
        picked.clear();
        result.hidden = true;
        sync();
    });
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!picked.size) return;
        if (!person.hidden && !person.value) { person.focus(); notify('Pilih orangnya dulu.', 'error'); return; }
        const button = form.querySelector('[type=submit]');
        button.disabled = true;
        try {
            const r = await sendJson(form.dataset.url, 'POST', { ids: [...picked], action: action.value, user_id: person.hidden ? null : Number(person.value) });
            notify(r.message, r.done ? 'success' : 'error');
            const list = document.createElement('ul');
            list.className = 'mb-0';
            (r.skipped ?? []).forEach((s) => list.append(Object.assign(document.createElement('li'), { textContent: `${s.title}: ${s.reason}` })));
            result.replaceChildren(...(list.children.length ? [Object.assign(document.createElement('strong'), { textContent: 'Dilewati:' }), list] : []));
            result.hidden = list.children.length === 0;
            picked.clear();
            document.dispatchEvent(new CustomEvent('ptm:tasks-changed')); // reloads this table on the same page, and any other list
        } catch (err) {
            notify(err.message, 'error');
        } finally {
            button.disabled = false;
            sync();
        }
    });
}
