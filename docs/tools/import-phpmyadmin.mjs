// Imports the module SQL ONCE through phpMyAdmin (Technical Guide 2.3 step 5), driven by a real browser.
// Usage: PMA_USER=ptm PMA_PASS=... node docs/tools/import-phpmyadmin.mjs <database> <file.sql>
// Refuses when the module tables (roles) already exist, so the import never runs twice on a database with data.
import { createRequire } from 'node:module';
import path from 'node:path';

const require = createRequire(process.env.PLAYWRIGHT_FROM ?? import.meta.url);
const { chromium } = require('@playwright/test');

const [db, file] = process.argv.slice(2);
const user = process.env.PMA_USER;
const password = process.env.PMA_PASS;
const baseUrl = process.env.PMA_URL ?? 'http://127.0.0.1:8180';
if (!db || !file || !user || !password) {
  console.error('Usage: PMA_USER=... PMA_PASS=... node docs/tools/import-phpmyadmin.mjs <database> <file.sql>');
  process.exit(2);
}

const browser = await chromium.launch();
const page = await browser.newPage();
try {
  await page.goto(baseUrl);
  await page.fill('#input_username', user);
  await page.fill('#input_password', password);
  await page.click('#input_go');
  await page.waitForSelector('#pma_navigation', { timeout: 30_000 });

  await page.goto(`${baseUrl}/index.php?route=/database/structure&db=${encodeURIComponent(db)}`);
  const content = await page.content();
  if (/\broles\b/.test(await page.locator('#structureTable').textContent().catch(() => '')) || /href="[^"]*table=roles/.test(content)) {
    console.error(`DITOLAK: tabel roles sudah ada di ${db}; SQL modul hanya diimpor sekali.`);
    process.exit(3);
  }

  await page.goto(`${baseUrl}/index.php?route=/database/import&db=${encodeURIComponent(db)}`);
  await page.setInputFiles('#input_import_file', path.resolve(file));
  await page.click('#buttonGo');
  const results = page.locator('.alert-success, .alert-danger, .error').first();
  await results.waitFor({ timeout: 120_000 });
  const text = (await results.textContent())?.replace(/\s+/g, ' ').trim() ?? '';
  console.log(text.slice(0, 300));
  await page.screenshot({ path: path.resolve('docs/evidence', `import-${db}.png`), fullPage: false });
  if (!(await results.getAttribute('class'))?.includes('alert-success')) process.exit(1);
} finally {
  await browser.close();
}
