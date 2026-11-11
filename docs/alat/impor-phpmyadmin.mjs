// Impor SQL modul SATU KALI lewat phpMyAdmin (Petunjuk Teknis 2.3 langkah 5), dijalankan dengan peramban sungguhan.
// Pakai: PMA_USER=ptm PMA_PASS=... node docs/alat/impor-phpmyadmin.mjs <basis-data> <berkas.sql>
// Menolak bila tabel modul (roles) sudah ada, supaya impor tidak pernah berulang pada database berisi data.
import { createRequire } from 'node:module';
import path from 'node:path';

const require = createRequire(process.env.PLAYWRIGHT_DARI ?? import.meta.url);
const { chromium } = require('@playwright/test');

const [db, berkas] = process.argv.slice(2);
const pengguna = process.env.PMA_USER;
const sandi = process.env.PMA_PASS;
const alamat = process.env.PMA_URL ?? 'http://127.0.0.1:8180';
if (!db || !berkas || !pengguna || !sandi) {
  console.error('Pakai: PMA_USER=... PMA_PASS=... node docs/alat/impor-phpmyadmin.mjs <basis-data> <berkas.sql>');
  process.exit(2);
}

const peramban = await chromium.launch();
const halaman = await peramban.newPage();
try {
  await halaman.goto(alamat);
  await halaman.fill('#input_username', pengguna);
  await halaman.fill('#input_password', sandi);
  await halaman.click('#input_go');
  await halaman.waitForSelector('#pma_navigation', { timeout: 30_000 });

  await halaman.goto(`${alamat}/index.php?route=/database/structure&db=${encodeURIComponent(db)}`);
  const isi = await halaman.content();
  if (/\broles\b/.test(await halaman.locator('#structureTable').textContent().catch(() => '')) || /href="[^"]*table=roles/.test(isi)) {
    console.error(`DITOLAK: tabel roles sudah ada di ${db}; SQL modul hanya diimpor sekali.`);
    process.exit(3);
  }

  await halaman.goto(`${alamat}/index.php?route=/database/import&db=${encodeURIComponent(db)}`);
  await halaman.setInputFiles('#input_import_file', path.resolve(berkas));
  await halaman.click('#buttonGo');
  const hasil = halaman.locator('.alert-success, .alert-danger, .error').first();
  await hasil.waitFor({ timeout: 120_000 });
  const teks = (await hasil.textContent())?.replace(/\s+/g, ' ').trim() ?? '';
  console.log(teks.slice(0, 300));
  await halaman.screenshot({ path: path.resolve('docs/bukti', `impor-${db}.png`), fullPage: false });
  if (!(await hasil.getAttribute('class'))?.includes('alert-success')) process.exit(1);
} finally {
  await peramban.close();
}
