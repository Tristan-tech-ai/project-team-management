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
