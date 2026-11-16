// Member form (U5.1): division rows follow the ticked companies, and a missing division or position is added in a dialog
// without leaving the form. A new division appears ticked; a new position becomes a choice for that company's divisions.
import { sendJson, notify } from './core';

export function initMemberForm(Modal) {
    const form = document.querySelector('[data-member-form]');
    if (!form) return;
    const rows = form.querySelector('[data-division-rows]');
    const empty = form.querySelector('[data-no-division]');
    let positions = {};
    try { positions = JSON.parse(form.dataset.positions || '{}'); } catch { positions = {}; }
    const ticked = () => [...form.querySelectorAll('[data-company-box]:checked')].map((b) => b.value);

    // Rows of companies that are not ticked are hidden and their fields not sent (the server only accepts divisions of ticked companies).
    const filter = () => {
        const companies = new Set(ticked());
        let shown = 0;
        rows.querySelectorAll('[data-division-row]').forEach((row) => {
            const on = companies.has(row.dataset.company);
            row.hidden = !on;
            row.querySelectorAll('input, select').forEach((field) => { field.disabled = !on; });
            shown += on ? 1 : 0;
        });
        empty.hidden = shown > 0;
        empty.querySelector('td').textContent = companies.size ? 'Belum ada divisi untuk company yang dicentang.' : 'Centang company di atas untuk memilih divisinya.';
    };
    form.addEventListener('change', (e) => { if (e.target.matches('[data-company-box]')) filter(); });
    filter();

    const positionOptions = (select, companyId, keep = '') => {
        select.replaceChildren(new Option('Tanpa jabatan', ''), ...(positions[companyId] ?? []).map((p) => new Option(p.name, p.id)));
        select.value = keep;
    };

    const modalEl = document.getElementById('org-add-modal');
    if (!modalEl) return;
    const modal = new Modal(modalEl);
    const dialog = modalEl.querySelector('form');
    const company = dialog.querySelector('[name=company_id]');
    const general = dialog.querySelector('[data-error-general]');
    let trigger = null;
    const clearErrors = () => {
        dialog.querySelectorAll('.is-invalid').forEach((x) => x.classList.remove('is-invalid'));
        dialog.querySelectorAll('[data-error-for]').forEach((x) => { x.textContent = ''; });
        general.classList.add('d-none');
    };

    form.addEventListener('click', (e) => {
        const button = e.target.closest('[data-org-add]');
        if (!button) return;
        const choices = ticked();
        if (!choices.length) {
            notify('Centang company dulu, lalu tambahkan divisi atau jabatannya.', 'error');
            form.querySelector('[data-company-box]')?.focus();
            return;
        }
        trigger = button;
        dialog.reset();
        clearErrors();
        // Only the ticked companies can be chosen; with one ticked company it is already chosen.
        [...company.options].forEach((o) => { o.hidden = !choices.includes(o.value); o.disabled = o.hidden; });
        company.value = choices[0];
        company.closest('.mb-3').hidden = choices.length === 1;
        modalEl.querySelector('[data-org-title]').textContent = button.dataset.title;
        modalEl.querySelector('[data-org-help]').textContent = button.dataset.orgAdd === 'division'
            ? 'Divisi baru langsung aktif dan tercentang untuk anggota ini.'
            : 'Jabatan baru langsung aktif dan bisa dipilih di kolom Jabatan.';
        modal.show();
    });
    modalEl.addEventListener('shown.bs.modal', () => dialog.querySelector('[name=name]').focus());
    modalEl.addEventListener('hidden.bs.modal', () => trigger?.focus());

    dialog.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        const submit = dialog.querySelector('[type=submit]');
        submit.disabled = true;
        try {
            const result = await sendJson(trigger.dataset.url, 'POST', { company_id: Number(company.value), name: dialog.querySelector('[name=name]').value, is_active: 1 });
            const cid = String(result.company_id);
            if (trigger.dataset.orgAdd === 'division') {
                const row = document.createElement('tr');
                row.dataset.divisionRow = '';
                row.dataset.company = cid;
                const id = result.id;
                row.innerHTML = `<td><input type="hidden" name="divisions[${id}][included]" value="0"><input class="form-check-input" type="checkbox" name="divisions[${id}][included]" value="1" id="d-${id}" checked></td>
                    <td><label class="form-check-label" for="d-${id}"></label></td><td class="muted"></td>
                    <td><label class="visually-hidden" for="p-${id}"></label><select class="form-select form-select-sm" name="divisions[${id}][position_id]" id="p-${id}" data-position-select data-company="${cid}"></select></td>`;
                row.querySelector('input[type=checkbox]').setAttribute('aria-label', `Ikut divisi ${result.name}`);
                row.querySelector(`label[for="d-${id}"]`).textContent = result.name;
                row.querySelector('td.muted').textContent = result.company ?? '';
                row.querySelector(`label[for="p-${id}"]`).textContent = `Jabatan di ${result.name}`;
                positionOptions(row.querySelector('select'), cid);
                rows.insertBefore(row, empty);
                filter();
            } else {
                positions[cid] = [...(positions[cid] ?? []), { id: result.id, name: result.name }].sort((a, b) => a.name.localeCompare(b.name, 'id'));
                rows.querySelectorAll(`[data-position-select][data-company="${CSS.escape(cid)}"]`).forEach((select) => positionOptions(select, cid, select.value));
            }
            modal.hide();
            notify(result.message ?? 'Tersimpan.');
        } catch (error) {
            let shown = false;
            Object.entries(error.errors ?? {}).forEach(([field, messages]) => {
                const target = dialog.querySelector(`[data-error-for="${field}"]`);
                if (target) {
                    target.closest('[hidden]')?.removeAttribute('hidden'); // the company choice is hidden when only one company is ticked
                    target.textContent = [messages].flat()[0];
                    dialog.querySelector(`[name="${field}"]`)?.classList.add('is-invalid');
                    shown = true;
                }
            });
            if (!shown) { general.textContent = error.message; general.classList.remove('d-none'); }
        } finally {
            submit.disabled = false;
        }
    });
}
