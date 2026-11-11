// Tangkapan layar halaman aplikasi yang sedang berjalan, per role dan lebar layar.
// Pakai: TANGKAP_EMAIL=... TANGKAP_SANDI=... node docs/alat/tangkap.mjs <dasar-url> <folder-keluaran> <lebar> <jalur1> [jalur2 ...]
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const require = createRequire(process.env.PLAYWRIGHT_DARI ?? import.meta.url);
const { chromium } = require('@playwright/test');
const [dasar, keluar, lebar, ...jalur] = process.argv.slice(2);
mkdirSync(keluar, { recursive: true });
const b = await chromium.launch();
const kt = await b.newContext({ viewport: { width: Number(lebar), height: Number(lebar) < 600 ? 844 : 900 }, deviceScaleFactor: 1 });
const h = await kt.newPage();
await h.goto(`${dasar}/masuk`);
await h.fill('input[name=email]', process.env.TANGKAP_EMAIL);
await h.fill('input[name=password]', process.env.TANGKAP_SANDI);
await h.click('button[type=submit]');
await h.waitForLoadState('networkidle');
for (const j of jalur) {
    await h.goto(`${dasar}${j}`);
    await h.waitForLoadState('networkidle');
    await h.waitForTimeout(400);
    const nama = `${lebar}-${j.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'beranda'}.png`;
    await h.screenshot({ path: path.join(keluar, nama), fullPage: true });
    console.log(path.join(keluar, nama));
}
await b.close();
