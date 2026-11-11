// Cetak HTML ke PDF A4 dengan nomor halaman. Pakai: node docs/alat/cetak-pdf.mjs <masuk.html> <keluar.pdf>
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

const require = createRequire(process.env.PLAYWRIGHT_DARI ?? import.meta.url);
const { chromium } = require('@playwright/test');
const [masuk, keluar] = process.argv.slice(2);
const b = await chromium.launch();
const h = await b.newPage();
await h.goto(pathToFileURL(path.resolve(masuk)).href, { waitUntil: 'load' });
await h.evaluate(() => document.fonts.ready);
await h.pdf({ path: keluar, format: 'A4', printBackground: true, preferCSSPageSize: true, displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate: '<div style="font-size:8px;color:#5a6678;width:100%;padding:0 16mm;display:flex;justify-content:space-between"><span>UAT Project &amp; Team Management</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>' });
await b.close();
console.log(keluar);
