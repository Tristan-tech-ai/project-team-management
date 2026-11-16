// LV-06 UI audit: 390 and 1366 px screenshots, axe (serious/critical), horizontal scroll, click-area size, and bottom nav on phones only.
// Usage: AUDIT_EMAIL=... AUDIT_PASSWORD=... node docs/tools/ui-audit.mjs <base-url> <app-name> <path1,path2,...>
// A path may end with @panel (task side panel open), @palette (Ctrl+K palette open with results) or @bulk (a task ticked,
// bulk bar shown), so the same checks also cover what only appears after a click.
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';

const require = createRequire(process.env.PLAYWRIGHT_FROM ?? import.meta.url);
const { chromium } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const [baseUrl, app, pathList] = process.argv.slice(2);
const paths = pathList.split(',').filter(Boolean);
const outDir = process.env.AUDIT_OUTPUT ?? 'docs/evidence/ui';
mkdirSync(outDir, { recursive: true });
const summary = [];

async function applyState(page, state) {
    if (state === 'panel') {
        // Lists and the calendar load their rows after the page; wait for the first task link.
        await page.locator('a[data-task-panel]:visible').first().waitFor({ timeout: 30_000 });
        await page.locator('a[data-task-panel]:visible').first().click();
        await page.locator('#task-panel [data-task-panel-content]').waitFor({ timeout: 30_000 });
    } else if (state === 'palette') {
        await page.keyboard.press('Control+k');
        await page.keyboard.type('la');
        await page.locator('#command-palette .palette-group').nth(1).waitFor({ timeout: 30_000 });
    } else if (state === 'bulk') {
        await page.locator('tbody [data-bulk-row]').first().waitFor({ timeout: 30_000 });
        await page.locator('tbody [data-bulk-row]').first().check();
        await page.locator('[data-bulk-form]:not([hidden])').waitFor();
    }
    await page.waitForTimeout(400);
}

const b = await chromium.launch();
for (const width of [390, 1366]) {
    const context = await b.newContext({ viewport: { width: width, height: width < 600 ? 844 : 900 }, isMobile: width < 600, hasTouch: width < 600 });
    const h = await context.newPage();
    await h.goto(`${baseUrl}/login`);
    await h.fill('input[name=email]', process.env.AUDIT_EMAIL);
    await h.fill('input[name=password]', process.env.AUDIT_PASSWORD);
    await h.click('button[type=submit]');
    await h.waitForLoadState('networkidle');
    for (const j of paths) {
        const [path, state] = j.split('@');
        const r = await h.goto(`${baseUrl}${path}`);
        await h.waitForLoadState('networkidle');
        await h.waitForTimeout(300);
        if (state) await applyState(h, state);
        const name = `${app}-${width}-${j.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'}`;
        try {
            await h.screenshot({ path: `${outDir}/${name}.png`, fullPage: true, animations: 'disabled', timeout: 15_000 });
        } catch {
            await h.screenshot({ path: `${outDir}/${name}.png`, animations: 'disabled', timeout: 15_000 }).catch(() => console.log(`  tangkapan gagal: ${j}`));
        }
        const axe = await new AxeBuilder({ page: h }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        const serious = axe.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical')
            .map((v) => `${v.id} (${v.nodes.length}): ${v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' | ')}`);
        // Compare with the requested width: on a phone a too-wide element zooms the layout out, which also grows innerWidth.
        const overflow = await h.evaluate((w) => Math.max(document.documentElement.scrollWidth, window.innerWidth) - w, width);
        const bottomNav = await h.evaluate(() => { const n = document.querySelector('.mobile-bottom-nav'); return n ? getComputedStyle(n).display !== 'none' : null; });
        // Technical Guide 6.1: click area at least 44 x 44 px on phones and 40 x 40 px on desktop.
        // A checkbox or radio counts together with its label, because clicking the label toggles it.
        const minSize = width < 600 ? 44 : 40;
        const smallTargets = await h.evaluate((min) => {
            const out = [];
            const sel = 'a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button], [role=tab], th.dt-orderable-asc, th.dt-orderable-desc';
            for (const el of document.querySelectorAll(sel)) {
                const cs = getComputedStyle(el);
                if (cs.display === 'none' || cs.visibility === 'hidden' || el.closest('[hidden], .visually-hidden, .visually-hidden-focusable:not(:focus)')) continue;
                const closed = el.closest('details:not([open])');
                if (closed && el.tagName !== 'SUMMARY') continue;
                if (el.matches('.skip-link') && document.activeElement !== el) continue;
                // DataTables puts a sort icon (role=button) inside the header cell; the whole header cell is the click area.
                if (el.matches('.dt-column-order') && el.closest('th.dt-orderable-asc, th.dt-orderable-desc')) continue;
                const rect = el.getBoundingClientRect();
                if (!rect.width || !rect.height) continue;
                let box = { left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom };
                if (el.matches('input[type=checkbox], input[type=radio]')) {
                    const label = (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) || el.closest('label');
                    if (label) {
                        const q = label.getBoundingClientRect();
                        box = { left: Math.min(box.left, q.left), top: Math.min(box.top, q.top), right: Math.max(box.right, q.right), bottom: Math.max(box.bottom, q.bottom) };
                    }
                }
                const w = Math.round(box.right - box.left), hgt = Math.round(box.bottom - box.top);
                if (w < min || hgt < min) {
                    const id = el.id ? `#${el.id}` : '';
                    const cls = typeof el.className === 'string' && el.className ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '';
                    out.push(`${el.tagName.toLowerCase()}${id}${cls} "${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 30)}" ${w}x${hgt}`);
                }
            }
            return out;
        }, minSize);
        // Technical Guide 6.1 table, measured on the rendered page (mobile = 390 px, desktop = 1366 px).
        const styleIssues = await h.evaluate((mobile) => {
            const out = [];
            const px = (v) => parseFloat(v) || 0;
            const visible = (el) => { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return r.width > 0 && r.height > 0 && cs.display !== 'none' && cs.visibility !== 'hidden' && !el.closest('[hidden], .visually-hidden, details:not([open]) > :not(summary)'); };
            const range = (label, value, lo, hi) => { if (value < lo - 0.5 || value > hi + 0.5) out.push(`${label} ${Math.round(value * 100) / 100} (${lo}-${hi})`); };
            const body = getComputedStyle(document.body);
            range('body font-size', px(body.fontSize), mobile ? 16 : 14, 16);
            range('body line-height ratio', px(body.lineHeight) / px(body.fontSize), 1.45, 1.6);
            const h1 = [...document.querySelectorAll('main h1, .app-main h1')].find(visible);
            if (h1) range('h1 font-size', px(getComputedStyle(h1).fontSize), mobile ? 22 : 26, mobile ? 24 : 30);
            for (const h2 of [...document.querySelectorAll('main h2, .app-main h2')].filter(visible).slice(0, 3)) {
                range(`h2 "${h2.textContent.trim().slice(0, 20)}" font-size`, px(getComputedStyle(h2).fontSize), mobile ? 18 : 20, mobile ? 20 : 24);
            }
            const main = document.querySelector('.app-main, main');
            if (main) range('page padding-left', px(getComputedStyle(main).paddingLeft), mobile ? 12 : 24, mobile ? 16 : 32);
            for (const card of [...document.querySelectorAll('.panel, .task-card')].filter(visible).slice(0, 5)) {
                const cs = getComputedStyle(card);
                range(`card ${card.className.split(' ')[0]} padding`, px(cs.paddingLeft), mobile ? 16 : 18, mobile ? 16 : 24);
                range(`card ${card.className.split(' ')[0]} radius`, px(cs.borderTopLeftRadius), 12, 16);
            }
            for (const el of [...document.querySelectorAll('.btn, input.form-control, select.form-select, input[type=search], input[type=date], input[type=datetime-local]')].filter(visible)) {
                if (el.closest('.fc')) continue;
                const hgt = el.getBoundingClientRect().height;
                if (hgt < (mobile ? 44 : 40) - 0.5 || hgt > (mobile ? 48 : 44) + 0.5) out.push(`input/button "${(el.innerText || el.name || el.className).trim().slice(0, 24)}" height ${Math.round(hgt)} (${mobile ? '44-48' : '40-44'})`);
            }
            const nav = document.querySelector('.mobile-bottom-nav');
            if (nav && visible(nav)) range('bottom nav height', nav.getBoundingClientRect().height, 64, 72);
            const side = document.querySelector('.desktop-sidebar, aside.admin-sidebar.desktop');
            if (side && visible(side) && !mobile) range('sidebar width', side.getBoundingClientRect().width, 232, 260);
            return [...new Set(out)];
        }, width < 600);
        const entry = { app, width, paths: j, http: r?.status(), serious, horizontalOverflow: overflow, bottomNavVisible: bottomNav, minTarget: minSize, smallTargets, styleIssues };
        summary.push(entry);
        const bad = serious.length || overflow > 1 || r?.status() !== 200 || smallTargets.length || styleIssues.length;
        console.log(`${bad ? 'PERIKSA' : 'ok     '} ${width} ${j} http=${r?.status()} axe=${serious.length} overflow=${overflow} bottomNav=${bottomNav} smallTargets=${smallTargets.length} style=${styleIssues.length}`
            + `${serious.length ? '\n    ' + serious.join('\n    ') : ''}${smallTargets.length ? '\n    ' + smallTargets.slice(0, 12).join('\n    ') : ''}`
            + `${styleIssues.length ? '\n    ' + styleIssues.slice(0, 12).join('\n    ') : ''}`);
    }
    await context.close();
}
await b.close();
writeFileSync(`${outDir}/audit-${app}.json`, JSON.stringify(summary, null, 1));
const problems = summary.filter((x) => x.serious.length || x.horizontalOverflow > 1 || x.http !== 200 || x.smallTargets.length || x.styleIssues.length
    || (app === 'team' && ((x.width === 390) !== x.bottomNavVisible)));
console.log(`\n${summary.length} pemeriksaan, ${problems.length} bermasalah`);
process.exit(problems.length ? 1 : 0);
