// Prints HTML to an A4 PDF with page numbers. Usage: node docs/tools/print-pdf.mjs <input.html> <output.pdf>
import { createRequire } from 'node:module';
import { pathToFileURL } from 'node:url';
import path from 'node:path';

const require = createRequire(process.env.PLAYWRIGHT_FROM ?? import.meta.url);
const { chromium } = require('@playwright/test');
const [inputHtml, outputPdf] = process.argv.slice(2);
const b = await chromium.launch();
const h = await b.newPage();
await h.goto(pathToFileURL(path.resolve(inputHtml)).href, { waitUntil: 'load' });
await h.evaluate(() => document.fonts.ready);
await h.pdf({ path: outputPdf, format: 'A4', printBackground: true, preferCSSPageSize: true, displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate: '<div style="font-size:8px;color:#5a6678;width:100%;padding:0 16mm;display:flex;justify-content:space-between"><span>UAT Project &amp; Team Management</span><span><span class="pageNumber"></span> / <span class="totalPages"></span></span></div>' });
await b.close();
console.log(outputPdf);
