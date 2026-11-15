// Yajra DataTables server-side (Technical Guide 9.3). Column definitions live in data-table-columns, never data-columns:
// DataTables 2 reads data-* attributes named after its own options and would replace the render functions below with plain strings.
// All text is rendered through DataTable.render.text(), so user
// content never becomes HTML; action buttons are built from URLs sent by the server.
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';

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
            return `<a href="${escape(a.url)}" class="btn btn-sm ${a.primary ? 'btn-primary' : 'btn-outline-primary'}">${escape(a.label)}</a>`;
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
        });
        const rememberFilters = () => {
            const url = new URL(window.location.href);
            new FormData(filter).forEach((v, k) => { if (v) url.searchParams.set(k, String(v)); else url.searchParams.delete(k); });
            window.history.replaceState(null, '', url);
        };
        filter?.addEventListener('change', () => { rememberFilters(); table.ajax.reload(); });
        filter?.addEventListener('submit', (e) => { e.preventDefault(); rememberFilters(); table.ajax.reload(); });
    });
}
