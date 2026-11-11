// Kanban (Petunjuk Teknis 9.4): SortableJS di desktop, dropdown status di HP, endpoint sama PATCH /tasks/{id}/status.
// Status baru hanya dianggap berhasil setelah API menjawab; bila ditolak, kartu dikembalikan ke posisi semula + pesan.
import Sortable from 'sortablejs';
import { kirimJson, notifikasi } from './inti';

function perbaruiHitungan(papan) {
    papan.querySelectorAll('[data-kanban-column]').forEach((kol) => {
        const n = kol.querySelectorAll('[data-task-id]').length;
        const lencana = papan.querySelector(`[data-hitung="${kol.dataset.status}"]`);
        if (lencana) lencana.textContent = String(n);
        const kosong = kol.querySelector('.kanban-kosong');
        if (kosong) kosong.hidden = n > 0;
    });
}

function urlStatus(kartu) {
    return kartu.querySelector('[data-ubah-status]')?.dataset.ubahStatus ?? `/tasks/${kartu.dataset.taskId}/status`;
}

export function pasangKanban() {
    document.querySelectorAll('[data-kanban]').forEach((papan) => {
        papan.querySelectorAll('[data-kanban-column]').forEach((kol) => {
            new Sortable(kol, {
                group: 'tasks',
                animation: 150,
                handle: '.task-handle',
                draggable: '[data-task-id]',
                ghostClass: 'kartu-bayang',
                onEnd: async (e) => {
                    if (e.from === e.to) return;
                    const kartu = e.item;
                    const ke = e.to.dataset.status;
                    kartu.classList.add('sedang-simpan');
                    try {
                        const hasil = await kirimJson(urlStatus(kartu), 'PATCH', { status: ke });
                        kartu.dataset.status = hasil.status;
                        const pilih = kartu.querySelector('[data-ubah-status]');
                        if (pilih) pilih.value = hasil.status;
                        notifikasi(hasil.message);
                    } catch (err) {
                        // Kembalikan kartu ke kolom dan urutan asal.
                        const acuan = e.from.querySelectorAll('[data-task-id]')[e.oldDraggableIndex] ?? e.from.querySelector('.kanban-kosong');
                        e.from.insertBefore(kartu, acuan ?? null);
                        notifikasi(err.message, 'error');
                    } finally {
                        kartu.classList.remove('sedang-simpan');
                        perbaruiHitungan(papan);
                    }
                },
            });
        });
    });

    // Dropdown status (HP atau saat seret gagal): endpoint yang sama.
    document.addEventListener('change', async (e) => {
        const pilih = e.target;
        if (!(pilih instanceof HTMLSelectElement) || !pilih.dataset.ubahStatus) return;
        const kartu = pilih.closest('[data-task-id]');
        const lama = kartu?.dataset.status ?? pilih.dataset.lama;
        pilih.disabled = true;
        try {
            const hasil = await kirimJson(pilih.dataset.ubahStatus, 'PATCH', { status: pilih.value });
            notifikasi(hasil.message);
            const papan = kartu?.closest('[data-kanban]');
            const tujuan = papan?.querySelector(`[data-kanban-column][data-status="${hasil.status}"]`);
            if (kartu && tujuan) {
                kartu.dataset.status = hasil.status;
                tujuan.prepend(kartu);
                perbaruiHitungan(papan);
            } else if (kartu) {
                kartu.dataset.status = hasil.status;
            }
        } catch (err) {
            pilih.value = lama;
            notifikasi(err.message, 'error');
        } finally {
            pilih.disabled = false;
        }
    });
}

// Checklist subtask dicentang langsung tanpa membuka halaman edit (Petunjuk Teknis 6.3).
export function pasangChecklist() {
    document.addEventListener('change', async (e) => {
        const kotak = e.target;
        if (!(kotak instanceof HTMLInputElement) || !kotak.dataset.centang) return;
        const label = kotak.closest('form')?.querySelector('label');
        kotak.disabled = true;
        try {
            const hasil = await kirimJson(kotak.dataset.centang, 'PATCH', { selesai: kotak.checked });
            label?.classList.toggle('text-decoration-line-through', hasil.selesai);
            label?.classList.toggle('teks-2', hasil.selesai);
            document.querySelectorAll('[data-checklist-jumlah]').forEach((el) => { el.textContent = hasil.jumlah; });
            document.querySelectorAll('[data-checklist-progres]').forEach((el) => { el.style.width = `${hasil.progres}%`; });
            notifikasi(hasil.message);
        } catch (err) {
            kotak.checked = !kotak.checked;
            notifikasi(err.message, 'error');
        } finally {
            kotak.disabled = false;
        }
    });

    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-isi-durasi]');
        if (!t) return;
        const isian = document.querySelector(t.dataset.target);
        if (isian) isian.value = t.dataset.isiDurasi;
    });
}
