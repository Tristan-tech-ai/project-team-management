// Yajra DataTables server-side (Petunjuk Teknis 9.3). Semua teks dirender lewat DataTable.render.text()
// sehingga isi pengguna tidak pernah menjadi HTML; tombol aksi dibangun dari URL yang dikirim server.
import $ from 'jquery';
import DataTable from 'datatables.net-bs5';

window.jQuery = window.$ = $;

const bahasa = {
    processing: 'Memuat...',
    search: 'Cari:',
    lengthMenu: 'Tampilkan _MENU_ baris',
    info: '_START_ sampai _END_ dari _TOTAL_ baris',
    infoEmpty: 'Belum ada data',
    infoFiltered: '(disaring dari _MAX_ baris)',
    zeroRecords: 'Tidak ada yang cocok dengan pencarian ini.',
    emptyTable: 'Belum ada data. Tambahkan yang pertama.',
    paginate: { first: 'Awal', last: 'Akhir', next: 'Berikutnya', previous: 'Sebelumnya' },
};

function lencana(teks, kelas) {
    const s = document.createElement('span');
    s.className = `lencana lencana-${kelas}`;
    s.textContent = teks;
    return s.outerHTML;
}

function escape(t) {
    const d = document.createElement('div');
    d.textContent = t ?? '';
    return d.innerHTML;
}

const penyaji = {
    teks: DataTable.render.text(),
    lencana: (data, type, row, meta) => {
        if (type !== 'display') return data;
        const kolom = meta.settings.aoColumns[meta.col].data;
        return lencana(data ?? '-', row[`${kolom}_kelas`] ?? 'netral');
    },
    progres: (data, type) => (type === 'display'
        ? `<div class="d-flex align-items-center gap-2"><div class="progres flex-grow-1" style="min-width:80px"><span style="width:${Number(data) || 0}%"></span></div><span class="angka small">${Number(data) || 0}%</span></div>`
        : data),
    aksi: (data, type, row) => {
        if (type !== 'display') return '';
        const tombol = (row.aksi ?? []).map((a) => {
            if (a.metode) {
                return `<form method="post" action="${escape(a.url)}" class="d-inline" ${a.konfirmasi ? `data-konfirmasi="${escape(a.konfirmasi)}"` : ''}>
                    <input type="hidden" name="_token" value="${escape(document.querySelector('meta[name="csrf-token"]').content)}">
                    <input type="hidden" name="_method" value="${escape(a.metode)}">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">${escape(a.label)}</button></form>`;
            }
            return `<a href="${escape(a.url)}" class="btn btn-sm btn-outline-primary">${escape(a.label)}</a>`;
        });
        return `<div class="d-flex gap-1 justify-content-end flex-wrap">${tombol.join('')}</div>`;
    },
};

export function pasangTabel() {
    document.querySelectorAll('table[data-tabel]').forEach((el) => {
        const kolom = JSON.parse(el.dataset.kolom).map((k) => ({
            data: k.data,
            name: k.name ?? k.data,
            orderable: k.orderable ?? true,
            searchable: k.searchable ?? true,
            className: k.kelas ?? '',
            render: penyaji[k.render ?? 'teks'],
        }));
        const filter = el.dataset.filter ? document.querySelector(el.dataset.filter) : null;
        const tabel = new DataTable(el, {
            processing: true,
            serverSide: true,
            ajax: {
                url: el.dataset.tabel,
                data: (d) => {
                    if (filter) new FormData(filter).forEach((v, k) => { d[k] = v; });
                },
            },
            columns: kolom,
            order: el.dataset.urut ? JSON.parse(el.dataset.urut) : [[0, 'asc']],
            pageLength: 25,
            language: bahasa,
            autoWidth: false,
        });
        filter?.addEventListener('change', () => tabel.ajax.reload());
        filter?.addEventListener('submit', (e) => { e.preventDefault(); tabel.ajax.reload(); });
    });
}
