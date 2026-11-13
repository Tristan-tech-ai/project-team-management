// Screenshots of the running app pages, per role and screen width.
// Usage: CAPTURE_EMAIL=... CAPTURE_PASSWORD=... node docs/tools/capture.mjs <base-url> <output-folder> <width> <path1> [path2 ...]
import { createRequire } from 'node:module';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

const require = createRequire(process.env.PLAYWRIGHT_FROM ?? import.meta.url);
const { chromium } = require('@playwright/test');
const [baseUrl, outDir, width, ...paths] = process.argv.slice(2);
mkdirSync(outDir, { recursive: true });
const b = await chromium.launch();
const context = await b.newContext({ viewport: { width: Number(width), height: Number(width) < 600 ? 844 : 900 }, deviceScaleFactor: 1 });
const h = await context.newPage();
await h.goto(`${baseUrl}/login`);
await h.fill('input[name=email]', process.env.CAPTURE_EMAIL);
await h.fill('input[name=password]', process.env.CAPTURE_PASSWORD);
await h.click('button[type=submit]');
await h.waitForLoadState('networkidle');
for (const j of paths) {
    await h.goto(`${baseUrl}${j}`);
    await h.waitForLoadState('networkidle');
    await h.waitForTimeout(400);
    const name = `${width}-${j.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'}.png`;
    await h.screenshot({ path: path.join(outDir, name), fullPage: true });
    console.log(path.join(outDir, name));
}
await b.close();
