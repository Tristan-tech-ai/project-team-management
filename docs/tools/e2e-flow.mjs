// End-to-end test in a real browser against the running admin-app (:8101) and team-app (:8102).
// Creates a project from a template through the UI, drags on the Kanban (accepted and refused -> card goes back), sets assignees,
// status dropdown + checklist on a phone (team-app 390 px), submit result, revision, approval, activity + deadline request, calendar.
// Usage: E2E_ADMIN_PASSWORD=... E2E_DEMO_PASSWORD=... node docs/tools/e2e-flow.mjs
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';

const require = createRequire(process.env.PLAYWRIGHT_FROM ?? import.meta.url);
const { chromium, expect } = require('@playwright/test');
const ADMIN = process.env.E2E_ADMIN ?? 'http://127.0.0.1:8101';
const TEAM = process.env.E2E_TEAM ?? 'http://127.0.0.1:8102';
const EVIDENCE = process.env.E2E_EVIDENCE ?? 'docs/evidence/tests/e2e';
mkdirSync(EVIDENCE, { recursive: true });
const name = `Campaign Sekolah ${new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '')}`;
const fileContent = `Riset audiens ${name}: siswa 13-17 tahun, kanal Instagram dan TikTok.`;
const results = [];
const record = (ok, step) => { results.push([ok, step]); console.log(`${ok ? 'ok  ' : 'GAGAL'} ${step}`); };

async function login(h, baseUrl, email, password) {
    await h.goto(`${baseUrl}/login`);
    await h.fill('input[name=email]', email);
    await h.fill('input[name=password]', password);
    await h.click('button[type=submit]');
    await h.waitForLoadState('networkidle');
}

const b = await chromium.launch();
let current = null;
try {
    // 1. A Manager creates a project from a template with three divisions, previewing first.
    const adminContext = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const a = await adminContext.newPage();
    current = a;
    const jsErrors = [];
    a.on('pageerror', (e) => jsErrors.push(e.message));
    await login(a, ADMIN, 'demo.manager@example.test', process.env.E2E_DEMO_PASSWORD);
    await a.goto(`${ADMIN}/projects/create`);
    await a.selectOption('select[name=project_template_id]', { label: 'Digital Marketing Campaign (Berkelanjutan)' });
    await expect(a.locator('[data-preview]')).toContainText('5 task', { timeout: 10_000 });
    record(true, 'pratinjau template menampilkan 5 task sebelum generate');
    await a.fill('input[name=name]', name);
    await a.selectOption('select[name=project_type]', 'ongoing');
    for (const d of ['Development', 'Creative', 'Digital Marketing']) await a.locator('label.btn', { hasText: d }).first().click();
    await a.locator('label.btn', { hasText: 'Demo Member' }).click();
    await a.click('button:has-text("Buat project dan task")');
    await a.waitForURL(/\/projects\/\d+\/board/);
    const projectId = a.url().match(/projects\/(\d+)/)[1];
    await expect(a.locator('[data-kanban-column][data-status=backlog] [data-task-id]')).toHaveCount(5);
    await expect(a.locator('.page-header')).toContainText('3 divisi');
    record(true, `project ${projectId} dibuat dari template: 5 task di Antrian, 3 divisi`);
    await a.screenshot({ path: `${EVIDENCE}/01-new-board.png` });

    // 2. Drag To Do -> In Progress (accepted), then In Progress -> Done (refused, the card goes back).
    const card = a.locator('[data-kanban-column][data-status=backlog] [data-task-id]').first();
    const cardId = await card.getAttribute('data-task-id');
    await card.locator('.task-handle').dragTo(a.locator('[data-kanban-column][data-status=in_progress]'));
    await expect(a.locator('.toast-area .alert-success').last()).toContainText('Dikerjakan', { timeout: 10_000 });
    await expect(a.locator(`[data-kanban-column][data-status=in_progress] [data-task-id="${cardId}"]`)).toHaveCount(1);
    await a.reload();
    await expect(a.locator(`[data-kanban-column][data-status=in_progress] [data-task-id="${cardId}"]`)).toHaveCount(1);
    record(true, 'seret ke Dikerjakan tersimpan di server (bertahan setelah muat ulang)');
    await a.locator(`[data-task-id="${cardId}"] .task-handle`).dragTo(a.locator('[data-kanban-column][data-status=done]'));
    await expect(a.locator('.toast-area .alert-danger').last()).toBeVisible({ timeout: 10_000 });
    await expect(a.locator(`[data-kanban-column][data-status=in_progress] [data-task-id="${cardId}"]`)).toHaveCount(1);
    await expect(a.locator(`[data-kanban-column][data-status=done] [data-task-id="${cardId}"]`)).toHaveCount(0);
    record(true, `seret ke Selesai ditolak API: "${(await a.locator('.toast-area .alert-danger').last().textContent()).trim()}", kartu kembali ke Dikerjakan`);
    await a.screenshot({ path: `${EVIDENCE}/02-rollback.png` });

    // 3. Set assignees: Demo Member + Demo Manager (multiple assignees) on the second task; add checklist items.
    const secondId = await a.locator('[data-kanban-column][data-status=backlog] [data-task-id]').first().getAttribute('data-task-id');
    await a.goto(`${ADMIN}/tasks/${secondId}`);
    await a.locator('form[action$="/assignees"] input[type=checkbox]').evaluateAll((els) => els.forEach((e) => { e.checked = true; }));
    await Promise.all([a.waitForNavigation(), a.click('form[action$="/assignees"] button[type=submit]')]);
    await expect(a.locator('main dl').first()).toContainText('Demo Member');
    for (const c of ['Riset kompetitor', 'Persona audiens']) {
        await a.fill('#new-subtask', c);
        await Promise.all([a.waitForNavigation(), a.click('form[action$="/subtasks"] button[type=submit]')]);
    }
    record(true, 'task punya 2 pelaksana + 2 checklist');
    await adminContext.close();

    // 4. Member in the Team App, 390 px phone: status dropdown, checklist ticked without opening an edit page, submit result.
    const teamContext = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    const t = await teamContext.newPage();
    current = t;
    t.on('pageerror', (e) => jsErrors.push(e.message));
    await login(t, TEAM, 'demo.member@example.test', process.env.E2E_DEMO_PASSWORD);
    await t.goto(`${TEAM}/projects/${projectId}/board`);
    await expect(t.locator('.mobile-bottom-nav')).toBeVisible();
    await expect(t.locator('.desktop-sidebar')).toBeHidden();
    const select = t.locator(`[data-task-id="${secondId}"] select[data-change-status]`);
    await select.selectOption('in_progress');
    await expect(t.locator('.toast-area .alert-success').last()).toContainText('Dikerjakan', { timeout: 10_000 });
    record(true, 'HP: dropdown status memindah task ke Dikerjakan (endpoint sama)');
    await select.selectOption('done');
    await expect(t.locator('.toast-area .alert-danger').last()).toBeVisible({ timeout: 10_000 });
    await expect(select).toHaveValue('in_progress');
    record(true, 'HP: member tidak bisa memilih Selesai tanpa review, pilihan kembali');
    await t.screenshot({ path: `${EVIDENCE}/03-team-board-390.png`, fullPage: true });
    await t.goto(`${TEAM}/tasks/${secondId}`);
    const checkbox = t.locator('[data-check-subtask]').first();
    await checkbox.check();
    await expect(t.locator('[data-checklist-count]')).toHaveText('1 / 2', { timeout: 10_000 });
    await t.reload();
    await expect(t.locator('[data-checklist-count]')).toHaveText('1 / 2');
    record(true, 'HP: checklist dicentang langsung, tersimpan setelah muat ulang');
    await t.fill('input[name=external_url]', 'https://drive.contoh.test/riset-audiens');
    await t.fill('textarea[name=submission_note]', `Riset audiens versi 1 project ${projectId}`);
    // The file is uploaded through the Team App; it must be readable in the Admin App (shared storage in admin-app storage).
    await t.setInputFiles('input[name=file]', { name: 'riset-audiens-v1.txt', mimeType: 'text/plain', buffer: Buffer.from(fileContent) });
    await t.click('button:has-text("Kirim untuk Review")');
    await expect(t.locator('main .pill').first()).toContainText('Review');
    record(true, 'HP: hasil dikirim (tautan + berkas), task masuk Review');
    await t.screenshot({ path: `${EVIDENCE}/04-team-task-review-390.png`, fullPage: true });
    await teamContext.close();

    // 5. The Manager requests a revision with a reason, then (after resubmission) approves.
    const managerContext = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const m = await managerContext.newPage();
    current = m;
    await login(m, ADMIN, 'demo.manager@example.test', process.env.E2E_DEMO_PASSWORD);
    await m.goto(`${ADMIN}/tasks/review`);
    await expect(m.locator('.panel', { hasText: `Riset audiens versi 1 project ${projectId}` })).toHaveCount(1);
    await m.goto(`${ADMIN}/tasks/${secondId}`);
    const fileLink = m.locator('a', { hasText: 'riset-audiens-v1.txt' }).first();
    await expect(fileLink).toBeVisible();
    const download = await m.request.get(new URL(await fileLink.getAttribute('href'), ADMIN).href);
    expect(download.status()).toBe(200);
    expect(await download.text()).toBe(fileContent);
    record(true, 'berkas yang diunggah di Team App terbuka di Admin App (isi sama)');
    await m.fill('#review textarea[name=review_note]', 'Tambahkan data usia 13-17 tahun');
    await m.click('#review button:has-text("Kembalikan untuk revisi")');
    await expect(m.locator('main .pill').first()).toContainText('Dikerjakan');
    record(true, 'revisi beralasan mengembalikan task ke Dikerjakan');
    await managerContext.close();
    const teamContext2 = await b.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const t2 = await teamContext2.newPage();
    current = t2;
    await login(t2, TEAM, 'demo.member@example.test', process.env.E2E_DEMO_PASSWORD);
    await t2.goto(`${TEAM}/tasks/${secondId}`);
    await expect(t2.locator('body')).toContainText('Tambahkan data usia 13-17 tahun');
    await t2.fill('input[name=external_url]', 'https://drive.contoh.test/riset-audiens-v2');
    await t2.click('button:has-text("Kirim untuk Review")');
    await teamContext2.close();
    const managerContext2 = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const m2 = await managerContext2.newPage();
    current = m2;
    await login(m2, ADMIN, 'demo.manager@example.test', process.env.E2E_DEMO_PASSWORD);
    await m2.goto(`${ADMIN}/tasks/${secondId}`);
    await m2.click('#review button:has-text("Terima")');
    await expect(m2.locator('main .pill').first()).toContainText('Selesai');
    const actualDone = (await m2.locator('dt', { hasText: 'Selesai aktual' }).locator('xpath=following-sibling::dd[1]').textContent()).trim();
    if (actualDone === '-' || actualDone === '') throw new Error(`tanggal selesai aktual kosong: "${actualDone}"`);
    await expect(m2.locator('main')).toContainText(`Riset audiens versi 1 project ${projectId}`);
    record(true, 'hasil kedua diterima: task Selesai, tanggal selesai aktual terisi, riwayat 2 hasil');

    // 6. Extra meeting + deadline request, approved; the initial target stays readable.
    await m2.goto(`${ADMIN}/projects/${projectId}/activities`);
    const fa = m2.locator('form[action$="/activities"]');
    await fa.locator('input[name=title]').fill('Rapat klien tambahan');
    await fa.locator('button[data-fill-duration="120"]').click();
    await fa.locator('select[name=impact_type]').selectOption('delay');
    await fa.locator('label.btn', { hasText: 'Demo Member' }).click();
    await fa.locator('details summary:has-text("Butuh geser deadline")').click();
    const proposedDeadline = new Date(Date.now() + 40 * 864e5).toISOString().slice(0, 16);
    await fa.locator('input[name=propose_deadline]').fill(proposedDeadline);
    await fa.locator('input[name=deadline_reason]').fill('Klien menambah kanal TikTok');
    await fa.locator('button:has-text("Simpan aktivitas")').click();
    await m2.waitForLoadState('networkidle');
    await expect(m2.locator('.timeline')).toContainText('Rapat klien tambahan');
    await m2.click('button:has-text("Setujui deadline baru")');
    await expect(m2.locator('body')).toContainText('Disetujui');
    await expect(m2.locator('body')).toContainText('Target awal');
    record(true, 'aktivitas berpeserta + pengajuan deadline disetujui, target awal tetap ditampilkan');
    await m2.screenshot({ path: `${EVIDENCE}/05-activity-schedule.png`, fullPage: true });

    // 7. Calendar: Schedule mode loads deadlines, Done mode loads the task that was just approved.
    await m2.goto(`${ADMIN}/projects/${projectId}/calendar`);
    await expect(m2.locator('.fc-event, .fc-list-event').first()).toBeVisible({ timeout: 15_000 });
    const scheduleCount = await m2.locator('.fc-event, .fc-list-event').count();
    await m2.click('button[data-calendar-mode=done]');
    await expect(m2.locator('.fc-event, .fc-list-event').first()).toContainText('Selesai:', { timeout: 15_000 });
    record(true, `kalender: mode Jadwal ${scheduleCount} kejadian, mode Selesai aktual menampilkan task yang diterima`);
    await managerContext2.close();

    // 8. Scenario 11.3 item 5: a recurring project enters a new period without closing the parent; old cards stay in the old period.
    if (process.env.E2E_RECURRING) {
        const recurringContext = await b.newContext({ viewport: { width: 1366, height: 900 } });
        const recurringPage = await recurringContext.newPage();
        current = recurringPage;
        await login(recurringPage, ADMIN, 'demo.manager@example.test', process.env.E2E_DEMO_PASSWORD);
        await recurringPage.goto(`${ADMIN}/projects/${process.env.E2E_RECURRING}`);
        const before = await recurringPage.locator('#period option').count();
        await recurringPage.click('button:has-text("Buka periode baru")');
        await recurringPage.click('#confirm-modal [data-confirm-yes]');
        await recurringPage.waitForURL(/\/board\?period=\d+/);
        await expect(recurringPage.locator('[data-kanban-column] [data-task-id]')).toHaveCount(5);
        await recurringPage.goto(`${ADMIN}/projects/${process.env.E2E_RECURRING}`);
        await expect(recurringPage.locator('#period option')).toHaveCount(before + 1);
        await expect(recurringPage.locator('.page-header')).toContainText('Berjalan');
        record(true, 'project berulang: periode baru dibuka (5 task dari template), project induk tetap Berjalan, periode lama tetap ada');
        await recurringPage.screenshot({ path: `${EVIDENCE}/06-new-period.png` });
        await recurringContext.close();
    }

    // 9. Item 6: Team pages at 1366 px without the bottom navigation and without empty space left by the navbar.
    const kd = await b.newContext({ viewport: { width: 1366, height: 900 } });
    const d = await kd.newPage();
    current = d;
    await login(d, TEAM, 'demo.member@example.test', process.env.E2E_DEMO_PASSWORD);
    await d.goto(`${TEAM}/tasks`);
    await expect(d.locator('.mobile-bottom-nav')).toBeHidden();
    await expect(d.locator('.desktop-sidebar')).toBeVisible();
    const pb2 = await d.evaluate(() => getComputedStyle(document.querySelector('.app-main')).paddingBottom);
    if (pb2 === '92px') throw new Error('padding bawah HP masih tersisa di desktop');
    record(true, `Tim 1366 px: bottom nav tersembunyi, sidebar tampil, padding bawah ${pb2}`);
    await d.screenshot({ path: `${EVIDENCE}/07-team-1366.png` });

    // 10. Item 7: a Member forcing Admin/Management URLs in the browser gets a friendly 403.
    await login(d, ADMIN, 'demo.member@example.test', process.env.E2E_DEMO_PASSWORD);
    for (const u of ['/management', '/settings/roles', '/master/companies']) {
        const r = await d.goto(`${ADMIN}${u}`);
        if (r.status() !== 403) throw new Error(`${u} memberi ${r.status()}`);
        await expect(d.locator('body')).toContainText('bukan untuk role-mu');
    }
    record(true, 'Member memaksa /management, /settings/roles, /master/companies: semua 403 dengan halaman ramah');
    await d.screenshot({ path: `${EVIDENCE}/08-member-403.png` });
    await kd.close();

    // 11. Item 8: DataTables with 520 rows, paging and search happen on the server.
    if (process.env.E2E_ARCHIVE) {
        const tableContext = await b.newContext({ viewport: { width: 1366, height: 900 } });
        const e = await tableContext.newPage();
        current = e;
        await login(e, ADMIN, 'admin@example.test', process.env.E2E_ADMIN_PASSWORD);
        const waitFor = (matches) => e.waitForResponse((r) => r.url().includes('/tasks/data') && matches(new URL(r.url()).searchParams));
        const [r1] = await Promise.all([waitFor((q) => q.get('start') === '0'), e.goto(`${ADMIN}/projects/${process.env.E2E_ARCHIVE}/tasks`)]);
        const j1 = await r1.json();
        if (j1.recordsTotal !== 520 || j1.data.length !== 25) throw new Error(`halaman 1: total ${j1.recordsTotal}, baris ${j1.data.length}`);
        await expect(e.locator('table tbody tr')).toHaveCount(25);
        const [r2] = await Promise.all([waitFor((q) => q.get('start') === '25'), e.locator('.dt-paging button').filter({ hasText: /^2$/ }).click()]);
        const j2 = await r2.json();
        const [r3] = await Promise.all([waitFor((q) => (q.get('search[value]') ?? '') === '512'), e.fill('.dt-search input', '512')]);
        const j3 = await r3.json();
        record(true, `DataTables: recordsTotal ${j1.recordsTotal}, tiap permintaan hanya ${j1.data.length} baris (length=25), halaman 2 start=25 (${j2.data.length} baris), cari "512" difilter server menjadi ${j3.recordsFiltered}`);
        await e.screenshot({ path: `${EVIDENCE}/09-datatables-520.png` });
        await tableContext.close();
    }

    if (jsErrors.length) record(false, `galat JS: ${jsErrors.join(' | ')}`);
} catch (e) {
    record(false, `berhenti: ${e.message.split('\n')[0]}`);
} finally {
    await b.close();
}
const failures = results.filter(([ok]) => !ok).length;
console.log(`\n${results.length - failures} lulus, ${failures} gagal`);
process.exit(failures ? 1 : 0);
