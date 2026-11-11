// Uji asap: masuk sebagai setiap role, buka setiap halaman menu yang terlihat, catat status HTTP, galat konsol, dan galat JS.
// Pakai: ASAP_SANDI_ADMIN=... ASAP_SANDI_DEMO=... node docs/alat/asap.mjs http://127.0.0.1:8101
import { createRequire } from 'node:module';

const require = createRequire(process.env.PLAYWRIGHT_DARI ?? import.meta.url);
const { chromium } = require('@playwright/test');
const dasar = process.argv[2] ?? 'http://127.0.0.1:8101';
const akun = [
    ['super_admin', 'admin@example.test', process.env.ASAP_SANDI_ADMIN],
    ['manager', 'demo.manager@example.test', process.env.ASAP_SANDI_DEMO],
    ['member', 'demo.member@example.test', process.env.ASAP_SANDI_DEMO],
];
let gagal = 0;
const peramban = await chromium.launch();
for (const [role, email, sandi] of akun) {
    const kt = await peramban.newContext({ viewport: { width: 1366, height: 900 } });
    const h = await kt.newPage();
    const galat = [];
    h.on('console', (m) => { if (m.type() === 'error') galat.push(`console: ${m.text()}`); });
    h.on('pageerror', (e) => galat.push(`js: ${e.message}`));
    await h.goto(`${dasar}/masuk`);
    await h.fill('input[name=email]', email);
    await h.fill('input[name=password]', sandi ?? '');
    await h.click('button[type=submit]');
    await h.waitForLoadState('networkidle');
    const pilih = process.env.ASAP_PILIH ?? 'aside .nav-anak a';
    const tautan = await h.$$eval(pilih, (as) => as.map((a) => [a.textContent.trim(), a.href]));
    const induk = await h.$$eval(process.env.ASAP_INDUK ?? 'aside .nav-induk > li > details > summary span', (s) => s.map((x) => x.textContent.trim()));
    for (const tambahan of (process.env.ASAP_TAMBAH ?? '').split(',').filter(Boolean)) tautan.push([tambahan, dasar + tambahan]);
    console.log(`\n== ${role}: ${induk.length} menu induk (${induk.join(', ')}), ${tautan.length} submenu`);
    for (const [judul, url] of tautan) {
        galat.length = 0;
        const r = await h.goto(url);
        await h.waitForLoadState('networkidle');
        const tabelGalat = await h.$('.dt-container .alert-danger, .dataTables_processing:visible');
        const st = r?.status() ?? 0;
        const ok = st === 200 && galat.length === 0;
        if (!ok) gagal++;
        console.log(`${ok ? 'ok  ' : 'GAGAL'} ${st} ${judul} ${url.replace(dasar, '')} ${galat.join(' | ')}${tabelGalat ? ' [tabel galat]' : ''}`);
    }
    await kt.close();
}
await peramban.close();
console.log(`\ngagal: ${gagal}`);
process.exit(gagal ? 1 : 0);
