// Smoke test: log in as every role, open every visible menu page, record HTTP status, console errors, and JS errors.
// Usage: SMOKE_ADMIN_PASSWORD=... SMOKE_DEMO_PASSWORD=... node docs/tools/smoke-test.mjs http://127.0.0.1:8101
import { createRequire } from 'node:module';

const require = createRequire(process.env.PLAYWRIGHT_FROM ?? import.meta.url);
const { chromium } = require('@playwright/test');
const baseUrl = process.argv[2] ?? 'http://127.0.0.1:8101';
// Each run is stamped so evidence from different runs never looks identical to an older run.
console.log(`uji asap ${baseUrl}, dijalankan ${new Date().toISOString()}`);
const accounts = [
    ['super_admin', 'admin@example.test', process.env.SMOKE_ADMIN_PASSWORD],
    ['manager', 'demo.manager@example.test', process.env.SMOKE_DEMO_PASSWORD],
    ['member', 'demo.member@example.test', process.env.SMOKE_DEMO_PASSWORD],
];
let failures = 0;
const browser = await chromium.launch();
for (const [role, email, password] of accounts) {
    const context = await browser.newContext({ viewport: { width: 1366, height: 900 } });
    const h = await context.newPage();
    const errors = [];
    h.on('console', (m) => { if (m.type() === 'error') errors.push(`console: ${m.text()}`); });
    h.on('pageerror', (e) => errors.push(`js: ${e.message}`));
    await h.goto(`${baseUrl}/login`);
    await h.fill('input[name=email]', email);
    await h.fill('input[name=password]', password ?? '');
    await h.click('button[type=submit]');
    await h.waitForLoadState('networkidle');
    const select = process.env.SMOKE_LINKS ?? 'aside.desktop .side-nav a.side-link';
    const links = await h.$$eval(select, (as) => as.map((a) => [a.textContent.trim(), a.href]));
    const groups = await h.$$eval(process.env.SMOKE_GROUPS ?? 'aside.desktop .side-single span, aside.desktop .side-heading span', (s) => s.map((x) => x.textContent.trim()));
    for (const extra of (process.env.SMOKE_EXTRA ?? '').split(',').filter(Boolean)) links.push([extra, baseUrl + extra]);
    console.log(`\n== ${role}: ${groups.length} menu induk (${groups.join(', ')}), ${links.length} submenu`);
    for (const [title, url] of links) {
        errors.length = 0;
        const r = await h.goto(url);
        await h.waitForLoadState('networkidle');
        const tableError = await h.$('.dt-container .alert-danger, .dataTables_processing:visible');
        // Every status column (render "badge") must show a label in every row; a blank cell means the renderer was lost.
        const blankBadges = await h.$$eval('table[data-table]', (tables) => tables.reduce((n, t) => {
            const cols = JSON.parse(t.dataset.tableColumns || '[]');
            const idx = cols.map((c, i) => (c.render === 'badge' ? i : -1)).filter((i) => i >= 0);
            const rows = [...t.querySelectorAll('tbody tr')].filter((r) => r.children.length === cols.length);
            return n + rows.reduce((m, r) => m + idx.filter((i) => !r.children[i].querySelector('.pill')).length, 0);
        }, 0));
        if (blankBadges) errors.push(`${blankBadges} status cell(s) without a label`);
        const st = r?.status() ?? 0;
        const ok = st === 200 && errors.length === 0;
        if (!ok) failures++;
        console.log(`${ok ? 'ok  ' : 'GAGAL'} ${st} ${title} ${url.replace(baseUrl, '')} ${errors.join(' | ')}${tableError ? ' [table error]' : ''}`);
    }
    await context.close();
}
await browser.close();
console.log(`\ngagal: ${failures}`);
process.exit(failures ? 1 : 0);
