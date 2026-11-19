// I0 usability metric: replays the core everyday flows the way a person would (only clicks and typing, no direct URLs after
// the start page) and counts clicks, fields filled, and page loads per flow. Run before and after a UI change and compare.
// Usage: UX_ADMIN_EMAIL=... UX_ADMIN_PASSWORD=... UX_DEMO_PASSWORD=... node docs/tools/ux-metrics.mjs <label> [flow,...]
// Output: docs/evidence/ux-metrics/<label>.json and a table on stdout.
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';

const require = createRequire(process.env.PLAYWRIGHT_FROM ?? import.meta.url);
const { chromium, expect } = require('@playwright/test');
const ADMIN = process.env.UX_ADMIN ?? 'http://127.0.0.1:8101';
const TEAM = process.env.UX_TEAM ?? 'http://127.0.0.1:8102';
const [label = 'run', only = ''] = process.argv.slice(2);
const OUT = 'docs/evidence/ux-metrics';
mkdirSync(OUT, { recursive: true });
const stamp = new Date().toISOString().slice(5, 16).replace(/[-:T]/g, '');
const shared = { taskTitle: `Riset kompetitor ${stamp}`, clientName: `Klien Baru ${stamp}` };

class Session {
    constructor(page) {
        this.page = page;
        this.clicks = 0;
        this.fields = 0;
        this.pages = 0;
        // Full page loads only. Same-document history entries (the side panel adds one so the phone Back button closes it,
        // filters rewrite the address) are not pages the person waits for.
        page.on('domcontentloaded', () => { this.pages++; });
    }
    reset() { this.clicks = 0; this.fields = 0; this.pages = 0; this.frozen = null; }
    // Stop counting: what follows only verifies the result (it may open another page directly).
    freeze() { this.frozen = { clicks: this.clicks, fields: this.fields, pages: this.pages }; }
    get counts() { return this.frozen ?? { clicks: this.clicks, fields: this.fields, pages: this.pages }; }
    async click(locator) { await locator.first().click(); this.clicks++; await this.page.waitForLoadState('networkidle'); }
    async fill(locator, value) { await locator.first().fill(value); this.fields++; }
    async choose(locator, value) { await locator.first().selectOption(value); this.fields++; }
    async check(locator) { await locator.first().check(); this.clicks++; }
    // A drag counts as one action, like a click.
    async drag(source, target, targetPosition) { await source.first().dragTo(target.first(), { targetPosition, steps: 12 }); this.clicks++; }
}

async function open(browser, base, email, password, phone = false) {
    const context = await browser.newContext(phone ? { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } : { viewport: { width: 1366, height: 900 } });
    const page = await context.newPage();
    await page.goto(`${base}/login`);
    await page.fill('input[name=email]', email);
    await page.fill('input[name=password]', password);
    await page.click('form[data-login-form] button[type=submit], form button[type=submit]');
    await page.waitForLoadState('networkidle');
    return new Session(page);
}

// Daftar Task of the showcase project; two of its members are noted for the PIC flows (looked up before counting starts).
async function projectTasks(s) {
    const found = await s.page.request.get(`${ADMIN}/search?keyword=${encodeURIComponent('Campaign PPDB')}`, { headers: { Accept: 'application/json' } });
    const project = (await found.json()).projects[0];
    const path = `${new URL(project.url).pathname}/tasks`;
    if (!shared.picOne) {
        await s.page.goto(`${ADMIN}${path}`);
        const options = s.page.locator('[data-bulk-user] option[value]:not([value=""])');
        await options.first().waitFor({ state: 'attached' });
        const people = await options.evaluateAll((os) => os.map((o) => [o.value, o.textContent.split(' (')[0]]));
        [[, shared.picOne], [shared.picTwoId]] = people;
    }
    return path;
}

const inFuture = (days) => new Date(Date.now() + days * 864e5).toISOString().slice(0, 16);
const admin = () => [ADMIN, process.env.UX_ADMIN_EMAIL, process.env.UX_ADMIN_PASSWORD];
const manager = () => [ADMIN, 'demo.manager@example.test', process.env.UX_DEMO_PASSWORD];
const member = () => [TEAM, 'demo.member@example.test', process.env.UX_DEMO_PASSWORD, true];

// Each flow: who, where it starts, the steps, and the check that proves it reached the goal.
const FLOWS = {
    'buka-dashboard': {
        name: 'Buka Dashboard dari halaman lain', who: manager, start: '/projects',
        async run(s) {
            await s.click(s.page.locator('.admin-sidebar.desktop a.side-link', { hasText: 'Dashboard' }));
            await expect(s.page).toHaveURL(/\/dashboard$/);
        },
    },
    'project-dari-template': {
        name: 'Buat project dari template', who: manager, start: '/dashboard',
        async run(s) {
            await s.click(s.page.getByRole('link', { name: 'Buat project' }));
            await s.choose(s.page.locator('select[name=project_template_id]'), { label: 'Website Development (Sekali jadi)' });
            await s.fill(s.page.locator('input[name=name]'), `Website Klien ${stamp}`);
            await s.click(s.page.locator('label.btn', { hasText: 'Development' }));
            await s.click(s.page.locator('label.btn', { hasText: 'Demo Member' }));
            await s.click(s.page.getByRole('button', { name: 'Buat project dan task' }));
            await expect(s.page).toHaveURL(/\/projects\/\d+\/board/);
        },
    },
    'task-dari-halaman-tasks': {
        name: 'Tambah task dari halaman Tasks', who: manager, start: '/dashboard',
        async run(s) {
            await s.click(s.page.locator('.admin-sidebar.desktop a.side-link', { hasText: 'Semua Task' }));
            await s.click(s.page.locator('a:has-text("Tambah task")'));
            // U9: the project is chosen on the same screen (no "Lanjut" step).
            await s.choose(s.page.locator('select[name=project_id]'), { label: 'Website Company Profile (Demo)' });
            await s.fill(s.page.locator('input[name=title]'), shared.taskTitle);
            await s.click(s.page.locator('label.btn', { hasText: 'Demo Member' }));
            await s.fill(s.page.locator('input[name=due_at]'), inFuture(7));
            await s.click(s.page.locator('button:has-text("Tambah ke Antrian")'));
            await expect(s.page.locator('body')).toContainText(shared.taskTitle);
        },
    },
    'mulai-task': {
        name: 'Member memulai task (HP)', who: member, start: '/',
        async run(s) {
            // I2: the card in Tugas Saya carries the Mulai button.
            await s.click(s.page.locator('.mobile-bottom-nav a', { hasText: 'Tasks' }));
            await s.click(s.page.locator('article.task-tile', { hasText: shared.taskTitle }).locator('button:has-text("Mulai")'));
            s.freeze();
            await s.page.goto(`${TEAM}/tasks?status=in_progress`);
            await expect(s.page.locator('article.task-tile', { hasText: shared.taskTitle })).toHaveCount(1);
        },
    },
    'ajukan-deadline': {
        name: 'Member mengajukan geser deadline (HP)', who: member, start: '/',
        async run(s) {
            await s.click(s.page.locator('.mobile-bottom-nav a', { hasText: 'Tasks' }));
            // I3: the task opens in the side panel over Task Saya.
            await s.click(s.page.getByRole('link', { name: shared.taskTitle }));
            const panel = s.page.locator('#task-panel');
            // The deadline request is folded on the task (rarely needed), so it opens first.
            await s.click(panel.locator('summary:has-text("Butuh geser deadline")'));
            await s.fill(panel.locator('form[action$="/schedule-changes"] input[name=proposed_due_at]'), inFuture(14));
            await s.fill(panel.locator('form[action$="/schedule-changes"] [name=reason]'), 'Menunggu materi dari klien');
            await s.click(panel.getByRole('button', { name: 'Ajukan' }));
            await expect(panel).toContainText('Menunggu materi dari klien', { timeout: 20000 });
        },
    },
    'kirim-hasil': {
        name: 'Member mengirim hasil (HP)', who: member, start: '/',
        async run(s) {
            await s.click(s.page.locator('.mobile-bottom-nav a', { hasText: 'Tasks' }));
            await s.click(s.page.getByRole('link', { name: shared.taskTitle }));
            const panel = s.page.locator('#task-panel');
            await s.fill(panel.locator('input[name=external_url]'), 'https://drive.contoh.test/riset-kompetitor');
            await s.fill(panel.locator('textarea[name=submission_note]'), 'Riset 5 kompetitor');
            await s.click(panel.getByRole('button', { name: 'Kirim untuk Review' }));
            await expect(panel.locator('.pill').first()).toContainText('Review', { timeout: 20000 });
        },
    },
    'terima-review': {
        name: 'Manager menerima hasil', who: manager, start: '/dashboard',
        async run(s) {
            await s.click(s.page.locator('.admin-sidebar.desktop a.side-link', { hasText: 'Menunggu Review' }));
            await s.click(s.page.getByRole('link', { name: shared.taskTitle }));
            const panel = s.page.locator('#task-panel');
            await s.click(panel.locator('#review').getByRole('button', { name: 'Terima' }));
            await expect(panel.locator('.pill').first()).toContainText('Selesai', { timeout: 20000 });
        },
    },
    'catat-aktivitas': {
        name: 'Member mencatat rapat (HP)', who: member, start: '/',
        async run(s) {
            await s.click(s.page.locator('a:has-text("Aktivitas saya")'));
            const direct = s.page.locator('a.btn', { hasText: 'Website Company Profile (Demo)' });
            if (await direct.count()) {
                await s.click(direct);
            } else {
                await s.choose(s.page.locator('select[name=project]'), { label: 'Website Company Profile (Demo)' });
                await s.click(s.page.locator('button:has-text("Catat aktivitas")'));
            }
            await s.fill(s.page.locator('input[name=title]'), `Rapat mingguan ${stamp}`);
            await s.click(s.page.locator('button[data-fill-duration="60"]'));
            await s.click(s.page.getByRole('button', { name: 'Simpan aktivitas' }));
            s.freeze();
            if (!(await s.page.locator('main').innerText()).includes(`Rapat mingguan ${stamp}`)) {
                s.frozen.note = 'setelah simpan, catatan baru tidak terlihat di halaman';
                await s.page.goto(`${TEAM}/activities`);
            }
            await expect(s.page.locator('main')).toContainText(`Rapat mingguan ${stamp}`);
        },
    },
    'tambah-anggota': {
        name: 'Super Admin menambah anggota', who: admin, start: '/dashboard',
        async run(s) {
            await s.click(s.page.locator('.admin-sidebar.desktop a.side-link', { hasText: 'Anggota Tim' }));
            await s.click(s.page.locator('a:has-text("Tambah anggota")'));
            await s.fill(s.page.locator('input[name=name]'), `Sinta Ayu ${stamp}`);
            await s.fill(s.page.locator('input[name=email]'), `sinta${stamp}@example.test`);
            for (const sel of ['input[name=password]', 'input[name=password_confirmation]']) {
                if (await s.page.locator(sel).count()) await s.fill(s.page.locator(sel), 'sandi-awal-12345');
            }
            await s.click(s.page.locator('main button[type=submit]:has-text("Simpan")'));
            await expect(s.page.locator('body')).toContainText(`Sinta Ayu ${stamp}`);
        },
    },
    'client-baru-di-project': {
        name: 'Buat project untuk client yang belum ada', who: admin, start: '/dashboard',
        async run(s) {
            // U5: the client is added inside the project form ("Client baru"), without leaving it.
            await s.click(s.page.locator('.admin-header').locator('button:has-text("Buat")'));
            await s.click(s.page.locator('.create-menu a', { hasText: 'Project' }));
            await s.click(s.page.getByRole('button', { name: 'Client baru' }));
            await s.fill(s.page.locator('#quick-add-modal input[name=name]'), shared.clientName);
            await s.click(s.page.locator('#quick-add-modal').getByRole('button', { name: 'Simpan dan pilih' }));
            await expect(s.page.locator('select[name=client_id] option:checked')).toHaveText(shared.clientName);
        },
    },
    'urutan-menu': {
        name: 'Pindahkan menu Kalender ke atas Task', who: admin, start: '/dashboard',
        async run(s) {
            // U6: drag the Calendar group above Tasks, then save.
            await s.click(s.page.locator('.admin-sidebar.desktop a.side-link', { hasText: 'Pengaturan Menu' }));
            const group = (title) => s.page.locator('.menu-group', { has: s.page.locator(`input[data-group-title][value="${title}"]`) });
            // Drop on the upper edge of the Task group so it lands above it.
            await s.drag(group('Kalender').locator('[data-group-handle]'), group('Task').locator('.menu-group-head'), { x: 40, y: 4 });
            await s.click(s.page.getByRole('button', { name: 'Simpan pengaturan' }));
            await expect(s.page.locator('.admin-sidebar.desktop .side-nav > *').nth(2)).toContainText('Kalender');
            s.freeze();
            // Restore the original order (not counted).
            await s.page.getByRole('button', { name: 'Turunkan kelompok Kalender' }).click();
            await s.page.getByRole('button', { name: 'Simpan pengaturan' }).click();
            await s.page.waitForLoadState('networkidle');
            await expect(s.page.locator('.admin-sidebar.desktop .side-nav > *').nth(2)).not.toContainText('Kalender');
        },
    },
    // I13: the same change (a new PIC for three tasks) one task at a time through the side panel, then in one go.
    'pic-satu-satu': {
        name: 'Ganti PIC 3 task satu per satu', who: manager, start: projectTasks,
        async run(s) {
            const panel = s.page.locator('#task-panel');
            const row = (i) => s.page.locator('tbody tr').nth(i);
            for (let i = 0; i < 3; i++) {
                await s.click(row(i).locator('a[data-task-panel]', { hasText: 'Buka' }));
                await panel.locator('select[name=pic_user_id]').waitFor({ timeout: 30000 });
                await s.choose(panel.locator('select[name=pic_user_id]'), { label: shared.picOne });
                await s.click(panel.getByRole('button', { name: 'Simpan PIC dan pelaksana' }));
                // The panel reloads the saved task (two server round trips; slow on a remote database).
                await expect(panel.locator('dd').first()).toHaveText(shared.picOne, { timeout: 30000 });
                await s.click(panel.locator('button[data-bs-dismiss]'));
                // The list behind reloads with the new PIC (third cell, after the tick box and the title) before the next task.
                await expect(row(i).locator('td').nth(2)).toHaveText(shared.picOne, { timeout: 30000 });
            }
        },
    },
    'pic-massal': {
        name: 'Ganti PIC 3 task sekaligus', who: manager, start: projectTasks,
        async run(s) {
            for (let i = 0; i < 3; i++) await s.check(s.page.locator('tbody tr').nth(i).locator('[data-bulk-row]'));
            await s.choose(s.page.locator('[data-bulk-action]'), 'pic');
            await s.choose(s.page.locator('[data-bulk-user]'), shared.picTwoId);
            await s.click(s.page.locator('[data-bulk-form]').getByRole('button', { name: 'Terapkan' }));
            await expect(s.page.locator('.toast-area')).toContainText('3 task diperbarui', { timeout: 30000 });
        },
    },
    'izin-hapus-task': {
        name: 'Beri Manager izin hapus task', who: admin, start: '/dashboard',
        async run(s) {
            // U7: tick table per role (Manager opens first), no permission codes.
            await s.click(s.page.locator('.admin-sidebar.desktop a.side-link', { hasText: 'Role & Hak Akses' }));
            await s.check(s.page.getByLabel('Hapus Task', { exact: true }));
            await s.click(s.page.getByRole('button', { name: 'Simpan hak akses Manager' }));
            await expect(s.page.getByLabel('Hapus Task', { exact: true })).toBeChecked();
            s.freeze();
            await s.page.getByLabel('Hapus Task', { exact: true }).uncheck();
            await s.page.getByRole('button', { name: 'Simpan hak akses Manager' }).click();
            await s.page.waitForLoadState('networkidle');
            await expect(s.page.getByLabel('Hapus Task', { exact: true })).not.toBeChecked();
        },
    },
};

const browser = await chromium.launch();
const results = [];
const sessions = new Map();
try {
    for (const [key, flow] of Object.entries(FLOWS)) {
        if (only && !only.split(',').includes(key)) continue;
        const who = flow.who();
        const id = who.slice(0, 2).join(' ');
        if (!sessions.has(id)) sessions.set(id, await open(browser, ...who));
        const s = sessions.get(id);
        const start = typeof flow.start === 'function' ? await flow.start(s) : flow.start;
        await s.page.goto(`${who[0]}${start}`);
        await s.page.waitForLoadState('networkidle');
        s.reset();
        const t0 = Date.now();
        try {
            await flow.run(s);
            const c = s.counts;
            results.push({ key, name: flow.name, ok: true, clicks: c.clicks, fields: c.fields, steps: c.clicks + c.fields, pages: c.pages, ms: Date.now() - t0, note: c.note });
        } catch (e) {
            results.push({ key, name: flow.name, ok: false, error: String(e.message).split('\n')[0].slice(0, 200) });
            await s.page.screenshot({ path: `${OUT}/${label}-${key}-failed.png`, fullPage: true }).catch(() => {});
        }
        const r = results.at(-1);
        console.log(r.ok ? `ok    ${r.name}: ${r.clicks} klik, ${r.fields} isian, ${r.pages} halaman${r.note ? ` (${r.note})` : ''}` : `GAGAL ${r.name}: ${r.error}`);
    }
} finally {
    await browser.close();
}
writeFileSync(`${OUT}/${label}.json`, JSON.stringify({ label, at: new Date().toISOString(), results }, null, 2));
console.log(`
| Alur | Klik | Isian | Langkah | Halaman dimuat | Catatan |
|---|---|---|---|---|---|`);
for (const r of results) console.log(r.ok ? `| ${r.name} | ${r.clicks} | ${r.fields} | ${r.steps} | ${r.pages} | ${r.note ?? ''} |` : `| ${r.name} | gagal | | | | ${r.error} |`);
process.exit(results.every((r) => r.ok) ? 0 : 1);
