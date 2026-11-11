// Fungsi bersama admin-app dan team-app: permintaan JSON ber-CSRF, notifikasi singkat, konfirmasi aksi berisiko.
const token = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function kirimJson(url, method = 'POST', body = {}) {
    const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token(), 'X-Requested-With': 'XMLHttpRequest' },
        body: method === 'GET' ? undefined : JSON.stringify(body),
        credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch { data = {}; }
    if (!res.ok) {
        const pesan = data.message || (data.errors ? Object.values(data.errors).flat()[0] : null) || 'Perubahan tidak tersimpan. Coba lagi.';
        const galat = new Error(pesan);
        galat.status = res.status;
        throw galat;
    }
    return data;
}

export function notifikasi(pesan, jenis = 'success') {
    let wadah = document.querySelector('.notifikasi');
    if (!wadah) {
        wadah = document.createElement('div');
        wadah.className = 'notifikasi';
        wadah.setAttribute('aria-live', 'polite');
        document.body.appendChild(wadah);
    }
    const el = document.createElement('div');
    el.className = `alert alert-${jenis === 'error' ? 'danger' : 'success'} shadow-sm mb-2`;
    el.setAttribute('role', jenis === 'error' ? 'alert' : 'status');
    el.textContent = pesan;
    wadah.appendChild(el);
    setTimeout(() => el.remove(), jenis === 'error' ? 6000 : 2800);
}

// <form data-konfirmasi="Nonaktifkan Company A?"> meminta konfirmasi lewat modal Bootstrap (bukan confirm()).
export function pasangKonfirmasi(Modal) {
    const modalEl = document.getElementById('modal-konfirmasi');
    if (!modalEl) return;
    const modal = new Modal(modalEl);
    let formTertunda = null;
    document.addEventListener('submit', (e) => {
        const f = e.target;
        if (!(f instanceof HTMLFormElement) || !f.dataset.konfirmasi || f.dataset.terkonfirmasi === '1') return;
        e.preventDefault();
        formTertunda = f;
        modalEl.querySelector('[data-isi]').textContent = f.dataset.konfirmasi;
        modal.show();
    });
    modalEl.querySelector('[data-ya]')?.addEventListener('click', () => {
        if (!formTertunda) return;
        formTertunda.dataset.terkonfirmasi = '1';
        modal.hide();
        formTertunda.requestSubmit();
    });
}

// Pesan flash dari server ditampilkan sebagai notifikasi singkat.
export function tampilkanFlash() {
    document.querySelectorAll('[data-flash]').forEach((el) => {
        notifikasi(el.dataset.flash, el.dataset.jenis || 'success');
        el.remove();
    });
}

// Wilayah tabel yang bisa digulir harus bisa dicapai keyboard (axe scrollable-region-focusable).
export function rapikanAksesibilitas() {
    document.querySelectorAll('.table-responsive').forEach((el) => {
        if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '0');
        if (!el.hasAttribute('aria-label')) {
            const judul = el.closest('.kartu')?.querySelector('h2, .kartu-judul')?.textContent?.trim();
            el.setAttribute('aria-label', judul ? `Tabel ${judul}` : 'Tabel data');
        }
        el.setAttribute('role', 'region');
    });
}
