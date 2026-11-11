// Audit UI LV-06: tangkapan layar 390 dan 1366 px, axe (serious/critical), gulir samping, dan bottom nav hanya HP.
// Pakai: AUDIT_EMAIL=... AUDIT_SANDI=... node docs/alat/audit-ui.mjs <dasar-url> <nama-app> <jalur1,jalur2,...>
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';

const require = createRequire(process.env.PLAYWRIGHT_DARI ?? import.meta.url);
const { chromium } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const [dasar, app, daftar] = process.argv.slice(2);
const jalur = daftar.split(',').filter(Boolean);
const keluar = process.env.AUDIT_KELUAR ?? 'docs/bukti/ui';
mkdirSync(keluar, { recursive: true });
const ringkas = [];
const b = await chromium.launch();
for (const lebar of [390, 1366]) {
    const kt = await b.newContext({ viewport: { width: lebar, height: lebar < 600 ? 844 : 900 }, isMobile: lebar < 600, hasTouch: lebar < 600 });
    const h = await kt.newPage();
    await h.goto(`${dasar}/masuk`);
    await h.fill('input[name=email]', process.env.AUDIT_EMAIL);
    await h.fill('input[name=password]', process.env.AUDIT_SANDI);
    await h.click('button[type=submit]');
    await h.waitForLoadState('networkidle');
    for (const j of jalur) {
        const r = await h.goto(`${dasar}${j}`);
        await h.waitForLoadState('networkidle');
        await h.waitForTimeout(300);
        const nama = `${app}-${lebar}-${j.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'beranda'}`;
        try {
            await h.screenshot({ path: `${keluar}/${nama}.png`, fullPage: true, animations: 'disabled', timeout: 15_000 });
        } catch {
            await h.screenshot({ path: `${keluar}/${nama}.png`, animations: 'disabled', timeout: 15_000 }).catch(() => console.log(`  tangkapan gagal: ${j}`));
        }
        const axe = await new AxeBuilder({ page: h }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        const serius = axe.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')
            .map((v) => `${v.id} (${v.nodes.length}): ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`);
        const samping = await h.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
        const navBawah = await h.evaluate(() => { const n = document.querySelector('.mobile-bottom-nav'); return n ? getComputedStyle(n).display !== 'none' : null; });
        const baris = { app, lebar, jalur: j, http: r?.status(), serius, gulirSamping: samping, navBawahTerlihat: navBawah };
        ringkas.push(baris);
        console.log(`${serius.length || samping > 1 || r?.status() !== 200 ? 'PERIKSA' : 'ok     '} ${lebar} ${j} http=${r?.status()} axe=${serius.length} samping=${samping} navBawah=${navBawah}${serius.length ? '\n    ' + serius.join('\n    ') : ''}`);
    }
    await kt.close();
}
await b.close();
writeFileSync(`${keluar}/audit-${app}.json`, JSON.stringify(ringkas, null, 1));
const masalah = ringkas.filter((x) => x.serius.length || x.gulirSamping > 1 || x.http !== 200 || (app === 'tim' && ((x.lebar === 390) !== x.navBawahTerlihat)));
console.log(`\n${ringkas.length} pemeriksaan, ${masalah.length} bermasalah`);
process.exit(masalah.length ? 1 : 0);
