// Uji ujung ke ujung di peramban sungguhan terhadap admin-app (:8101) dan team-app (:8102) yang sedang berjalan.
// Membuat project dari template lewat UI, seret Kanban (berhasil dan ditolak -> kartu kembali), atur pelaksana,
// dropdown status + checklist di HP (team-app 390 px), kirim hasil, revisi, terima, aktivitas + pengajuan deadline, kalender.
// Pakai: E2E_SANDI_ADMIN=... E2E_SANDI_DEMO=... node docs/alat/e2e-alur.mjs
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire(process.env.PLAYWRIGHT_DARI ?? import.meta.url);
const { chromium, expect } = require('@playwright/test');
const ADMIN = process.env.E2E_ADMIN ?? 'http://127.0.0.1:8101';
const TIM = process.env.E2E_TIM ?? 'http://127.0.0.1:8102';
const BUKTI = process.env.E2E_BUKTI ?? 'docs/bukti/e2e';
mkdirSync(BUKTI, { recursive: true });
const nama = `Campaign Sekolah ${new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '')}`;
const isiBerkas = `Riset audiens ${nama}: siswa 13-17 tahun, kanal Instagram dan TikTok.`;
const hasil = [];
const catat = (ok, langkah) => { hasil.push([ok, langkah]); console.log(`${ok ? 'ok  ' : 'GAGAL'} ${langkah}`); };

async function masuk(h, dasar, email, sandi) {
    await h.goto(`${dasar}/masuk`);
    await h.fill('input[name=email]', email);
    await h.fill('input[name=password]', sandi);
    await h.click('button[type=submit]');
    await h.waitForLoadState('networkidle');
}

const b = await chromium.launch();
let kini = null;
try {
    // 1. Manager membuat project dari template, tiga divisi, preview dulu.
    const ka = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const a = await ka.newPage();
    kini = a;
    const galatJs = [];
    a.on('pageerror', (e) => galatJs.push(e.message));
    await masuk(a, ADMIN, 'demo.manager@example.test', process.env.E2E_SANDI_DEMO);
    await a.goto(`${ADMIN}/projects/create`);
    await a.selectOption('select[name=project_template_id]', { label: 'Digital Marketing Campaign (Berkelanjutan)' });
    await expect(a.locator('[data-pratinjau]')).toContainText('5 task', { timeout: 10_000 });
    catat(true, 'pratinjau template menampilkan 5 task sebelum generate');
    await a.fill('input[name=name]', nama);
    await a.selectOption('select[name=project_type]', 'ongoing');
    for (const d of ['Development', 'Creative', 'Digital Marketing']) await a.locator('label.btn', { hasText: d }).first().click();
    await a.locator('label.btn', { hasText: 'Demo Member' }).click();
    await a.click('button:has-text("Buat project dan task")');
    await a.waitForURL(/\/projects\/\d+\/board/);
    const projectId = a.url().match(/projects\/(\d+)/)[1];
    await expect(a.locator('[data-kanban-column][data-status=backlog] [data-task-id]')).toHaveCount(5);
    await expect(a.locator('.kepala-halaman')).toContainText('3 divisi');
    catat(true, `project ${projectId} dibuat dari template: 5 task di Antrian, 3 divisi`);
    await a.screenshot({ path: `${BUKTI}/01-board-baru.png` });

    // 2. Seret Antrian -> Dikerjakan (berhasil), lalu Dikerjakan -> Selesai (ditolak, kartu kembali).
    const kartu = a.locator('[data-kanban-column][data-status=backlog] [data-task-id]').first();
    const idKartu = await kartu.getAttribute('data-task-id');
    await kartu.locator('.task-handle').dragTo(a.locator('[data-kanban-column][data-status=in_progress]'));
    await expect(a.locator('.notifikasi .alert-success').last()).toContainText('Dikerjakan', { timeout: 10_000 });
    await expect(a.locator(`[data-kanban-column][data-status=in_progress] [data-task-id="${idKartu}"]`)).toHaveCount(1);
    await a.reload();
    await expect(a.locator(`[data-kanban-column][data-status=in_progress] [data-task-id="${idKartu}"]`)).toHaveCount(1);
    catat(true, 'seret ke Dikerjakan tersimpan di server (bertahan setelah muat ulang)');
    await a.locator(`[data-task-id="${idKartu}"] .task-handle`).dragTo(a.locator('[data-kanban-column][data-status=done]'));
    await expect(a.locator('.notifikasi .alert-danger').last()).toBeVisible({ timeout: 10_000 });
    await expect(a.locator(`[data-kanban-column][data-status=in_progress] [data-task-id="${idKartu}"]`)).toHaveCount(1);
    await expect(a.locator(`[data-kanban-column][data-status=done] [data-task-id="${idKartu}"]`)).toHaveCount(0);
    catat(true, `seret ke Selesai ditolak API: "${(await a.locator('.notifikasi .alert-danger').last().textContent()).trim()}", kartu kembali ke Dikerjakan`);
    await a.screenshot({ path: `${BUKTI}/02-rollback.png` });

    // 3. Atur pelaksana: Demo Member + Demo Manager (multi assignee) pada task kedua; tambah checklist.
    const idKedua = await a.locator('[data-kanban-column][data-status=backlog] [data-task-id]').first().getAttribute('data-task-id');
    await a.goto(`${ADMIN}/tasks/${idKedua}`);
    await a.locator('form[action$="/assignees"] input[type=checkbox]').evaluateAll((els) => els.forEach((e) => { e.checked = true; }));
    await Promise.all([a.waitForNavigation(), a.click('form[action$="/assignees"] button[type=submit]')]);
    await expect(a.locator('main dl').first()).toContainText('Demo Member');
    for (const c of ['Riset kompetitor', 'Persona audiens']) {
        await a.fill('#sub-baru', c);
        await Promise.all([a.waitForNavigation(), a.click('form[action$="/subtasks"] button[type=submit]')]);
    }
    catat(true, 'task punya 2 pelaksana + 2 checklist');
    await ka.close();

    // 4. Member di aplikasi Tim, HP 390 px: dropdown status, checklist dicentang tanpa membuka halaman edit, kirim hasil.
    const kt = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const t = await kt.newPage();
    kini = t;
    t.on('pageerror', (e) => galatJs.push(e.message));
    await masuk(t, TIM, 'demo.member@example.test', process.env.E2E_SANDI_DEMO);
    await t.goto(`${TIM}/projects/${projectId}/board`);
    await expect(t.locator('.mobile-bottom-nav')).toBeVisible();
    await expect(t.locator('.desktop-sidebar')).toBeHidden();
    const pilih = t.locator(`[data-task-id="${idKedua}"] select[data-ubah-status]`);
    await pilih.selectOption('in_progress');
    await expect(t.locator('.notifikasi .alert-success').last()).toContainText('Dikerjakan', { timeout: 10_000 });
    catat(true, 'HP: dropdown status memindah task ke Dikerjakan (endpoint sama)');
    await pilih.selectOption('done');
    await expect(t.locator('.notifikasi .alert-danger').last()).toBeVisible({ timeout: 10_000 });
    await expect(pilih).toHaveValue('in_progress');
    catat(true, 'HP: member tidak bisa memilih Selesai tanpa review, pilihan kembali');
    await t.screenshot({ path: `${BUKTI}/03-tim-board-390.png`, fullPage: true });
    await t.goto(`${TIM}/tasks/${idKedua}`);
    const kotak = t.locator('[data-centang]').first();
    await kotak.check();
    await expect(t.locator('[data-checklist-jumlah]')).toHaveText('1 / 2', { timeout: 10_000 });
    await t.reload();
    await expect(t.locator('[data-checklist-jumlah]')).toHaveText('1 / 2');
    catat(true, 'HP: checklist dicentang langsung, tersimpan setelah muat ulang');
    await t.fill('input[name=external_url]', 'https://drive.contoh.test/riset-audiens');
    await t.fill('textarea[name=submission_note]', 'Riset audiens versi 1');
    // Berkas diunggah lewat Team App; harus terbaca di Admin App (penyimpanan bersama di storage admin-app).
    await t.setInputFiles('input[name=berkas]', { name: 'riset-audiens-v1.txt', mimeType: 'text/plain', buffer: Buffer.from(isiBerkas) });
    await t.click('button:has-text("Kirim untuk Review")');
    await expect(t.locator('main .lencana').first()).toContainText('Review');
    catat(true, 'HP: hasil dikirim (tautan + berkas), task masuk Review');
    await t.screenshot({ path: `${BUKTI}/04-tim-task-review-390.png`, fullPage: true });
    await kt.close();

    // 5. Manager meminta revisi beralasan, lalu (setelah kirim ulang) menerima.
    const km = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const m = await km.newPage();
    kini = m;
    await masuk(m, ADMIN, 'demo.manager@example.test', process.env.E2E_SANDI_DEMO);
    await m.goto(`${ADMIN}/tasks/review`);
    await expect(m.locator('.kartu', { hasText: 'Riset audiens versi 1' })).toHaveCount(1);
    await m.goto(`${ADMIN}/tasks/${idKedua}`);
    const tautanBerkas = m.locator('a', { hasText: 'riset-audiens-v1.txt' }).first();
    await expect(tautanBerkas).toBeVisible();
    const unduhan = await m.request.get(new URL(await tautanBerkas.getAttribute('href'), ADMIN).href);
    expect(unduhan.status()).toBe(200);
    expect(await unduhan.text()).toBe(isiBerkas);
    catat(true, 'berkas yang diunggah di Team App terbuka di Admin App (isi sama)');
    await m.fill('#tinjau textarea[name=review_note]', 'Tambahkan data usia 13-17 tahun');
    await m.click('#tinjau button:has-text("Kembalikan untuk revisi")');
    await expect(m.locator('main .lencana').first()).toContainText('Dikerjakan');
    catat(true, 'revisi beralasan mengembalikan task ke Dikerjakan');
    await km.close();
    const kt2 = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const t2 = await kt2.newPage();
    kini = t2;
    await masuk(t2, TIM, 'demo.member@example.test', process.env.E2E_SANDI_DEMO);
    await t2.goto(`${TIM}/tasks/${idKedua}`);
    await expect(t2.locator('body')).toContainText('Tambahkan data usia 13-17 tahun');
    await t2.fill('input[name=external_url]', 'https://drive.contoh.test/riset-audiens-v2');
    await t2.click('button:has-text("Kirim untuk Review")');
    await kt2.close();
    const km2 = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const m2 = await km2.newPage();
    kini = m2;
    await masuk(m2, ADMIN, 'demo.manager@example.test', process.env.E2E_SANDI_DEMO);
    await m2.goto(`${ADMIN}/tasks/${idKedua}`);
    await m2.click('#tinjau button:has-text("Terima")');
    await expect(m2.locator('main .lencana').first()).toContainText('Selesai');
    const selesaiAktual = (await m2.locator('dt', { hasText: 'Selesai aktual' }).locator('xpath=following-sibling::dd[1]').textContent()).trim();
    if (selesaiAktual === '-' || selesaiAktual === '') throw new Error(`tanggal selesai aktual kosong: "${selesaiAktual}"`);
    await expect(m2.locator('main')).toContainText('Riset audiens versi 1');
    catat(true, 'hasil kedua diterima: task Selesai, tanggal selesai aktual terisi, riwayat 2 hasil');

    // 6. Rapat tambahan + pengajuan deadline, disetujui; target awal tetap terbaca.
    await m2.goto(`${ADMIN}/projects/${projectId}/activities`);
    const fa = m2.locator('form[action$="/activities"]');
    await fa.locator('input[name=title]').fill('Rapat klien tambahan');
    await fa.locator('button[data-isi-durasi="120"]').click();
    await fa.locator('select[name=impact_type]').selectOption('delay');
    await fa.locator('label.btn', { hasText: 'Demo Member' }).click();
    await fa.locator('details summary:has-text("Butuh geser deadline")').click();
    const usul = new Date(Date.now() + 40 * 864e5).toISOString().slice(0, 16);
    await fa.locator('input[name=ajukan_deadline]').fill(usul);
    await fa.locator('input[name=alasan_deadline]').fill('Klien menambah kanal TikTok');
    await fa.locator('button:has-text("Simpan aktivitas")').click();
    await m2.waitForLoadState('networkidle');
    await expect(m2.locator('.timeline')).toContainText('Rapat klien tambahan');
    await m2.click('button:has-text("Setujui deadline baru")');
    await expect(m2.locator('body')).toContainText('Disetujui');
    await expect(m2.locator('body')).toContainText('Target awal');
    catat(true, 'aktivitas berpeserta + pengajuan deadline disetujui, target awal tetap ditampilkan');
    await m2.screenshot({ path: `${BUKTI}/05-aktivitas-jadwal.png`, fullPage: true });

    // 7. Kalender: mode Jadwal memuat deadline, mode Selesai memuat task yang baru diterima.
    await m2.goto(`${ADMIN}/projects/${projectId}/calendar`);
    await expect(m2.locator('.fc-event, .fc-list-event').first()).toBeVisible({ timeout: 15_000 });
    const jadwal = await m2.locator('.fc-event, .fc-list-event').count();
    await m2.click('button[data-mode-kalender=selesai]');
    await expect(m2.locator('.fc-event, .fc-list-event').first()).toContainText('Selesai:', { timeout: 15_000 });
    catat(true, `kalender: mode Jadwal ${jadwal} kejadian, mode Selesai aktual menampilkan task yang diterima`);
    await km2.close();

    // 8. Skenario 11.3 butir 5: project berulang memasuki periode baru tanpa menutup induk; kartu lama tetap di periode lama.
    if (process.env.E2E_BERULANG) {
        const kb = await b.newContext({ viewport: { width: 1366, height: 900 } });
        const pb = await kb.newPage();
        kini = pb;
        await masuk(pb, ADMIN, 'demo.manager@example.test', process.env.E2E_SANDI_DEMO);
        await pb.goto(`${ADMIN}/projects/${process.env.E2E_BERULANG}`);
        const sebelum = await pb.locator('#periode option').count();
        await pb.click('button:has-text("Buka periode baru")');
        await pb.click('#modal-konfirmasi [data-ya]');
        await pb.waitForURL(/\/board\?periode=\d+/);
        await expect(pb.locator('[data-kanban-column] [data-task-id]')).toHaveCount(5);
        await pb.goto(`${ADMIN}/projects/${process.env.E2E_BERULANG}`);
        await expect(pb.locator('#periode option')).toHaveCount(sebelum + 1);
        await expect(pb.locator('.kepala-halaman')).toContainText('Berjalan');
        catat(true, 'project berulang: periode baru dibuka (5 task dari template), project induk tetap Berjalan, periode lama tetap ada');
        await pb.screenshot({ path: `${BUKTI}/06-periode-baru.png` });
        await kb.close();
    }

    // 9. Butir 6: halaman Tim di 1366 px tanpa bottom navigation dan tanpa ruang kosong bekas navbar.
    const kd = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const d = await kd.newPage();
    kini = d;
    await masuk(d, TIM, 'demo.member@example.test', process.env.E2E_SANDI_DEMO);
    await d.goto(`${TIM}/tasks`);
    await expect(d.locator('.mobile-bottom-nav')).toBeHidden();
    await expect(d.locator('.desktop-sidebar')).toBeVisible();
    const pb2 = await d.evaluate(() => getComputedStyle(document.querySelector('.app-main')).paddingBottom);
    if (pb2 === '92px') throw new Error('padding bawah HP masih tersisa di desktop');
    catat(true, `Tim 1366 px: bottom nav tersembunyi, sidebar tampil, padding bawah ${pb2}`);
    await d.screenshot({ path: `${BUKTI}/07-tim-1366.png` });

    // 10. Butir 7: Member memaksa URL Admin/Management di peramban: 403 ramah.
    await masuk(d, ADMIN, 'demo.member@example.test', process.env.E2E_SANDI_DEMO);
    for (const u of ['/management', '/settings/roles', '/master/companies']) {
        const r = await d.goto(`${ADMIN}${u}`);
        if (r.status() !== 403) throw new Error(`${u} memberi ${r.status()}`);
        await expect(d.locator('body')).toContainText('bukan untuk role-mu');
    }
    catat(true, 'Member memaksa /management, /settings/roles, /master/companies: semua 403 dengan halaman ramah');
    await d.screenshot({ path: `${BUKTI}/08-member-403.png` });
    await kd.close();

    // 11. Butir 8: DataTables 520 baris, paging dan pencarian terjadi di server.
    if (process.env.E2E_ARSIP) {
        const ke = await b.newContext({ viewport: { width: 1366, height: 900 } });
        const e = await ke.newPage();
        kini = e;
        await masuk(e, ADMIN, 'admin@example.test', process.env.E2E_SANDI_ADMIN);
        const tunggu = (cocok) => e.waitForResponse((r) => r.url().includes('/tasks/data') && cocok(new URL(r.url()).searchParams));
        const [r1] = await Promise.all([tunggu((q) => q.get('start') === '0'), e.goto(`${ADMIN}/projects/${process.env.E2E_ARSIP}/tasks`)]);
        const j1 = await r1.json();
        if (j1.recordsTotal !== 520 || j1.data.length !== 25) throw new Error(`halaman 1: total ${j1.recordsTotal}, baris ${j1.data.length}`);
        await expect(e.locator('table tbody tr')).toHaveCount(25);
        const [r2] = await Promise.all([tunggu((q) => q.get('start') === '25'), e.locator('.dt-paging button').filter({ hasText: /^2$/ }).click()]);
        const j2 = await r2.json();
        const [r3] = await Promise.all([tunggu((q) => (q.get('search[value]') ?? '') === '512'), e.fill('.dt-search input', '512')]);
        const j3 = await r3.json();
        catat(true, `DataTables: recordsTotal ${j1.recordsTotal}, tiap permintaan hanya ${j1.data.length} baris (length=25), halaman 2 start=25 (${j2.data.length} baris), cari "512" difilter server menjadi ${j3.recordsFiltered}`);
        await e.screenshot({ path: `${BUKTI}/09-datatables-520.png` });
        await ke.close();
    }

    if (galatJs.length) catat(false, `galat JS: ${galatJs.join(' | ')}`);
} catch (e) {
    catat(false, `berhenti: ${e.message.split('\n')[0]}`);
} finally {
    await b.close();
}
const gagal = hasil.filter(([ok]) => !ok).length;
console.log(`\n${hasil.length - gagal} lulus, ${gagal} gagal`);
process.exit(gagal ? 1 : 0);
