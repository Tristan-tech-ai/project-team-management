import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

import { transform } from 'lightningcss';

// Bootstrap 5.3 stays the base, but rules that need a component neither app uses (no class in views, scripts, DataTables or
// FullCalendar markup) are left out of the build: about 8 KB gzip less CSS on every page. The list is checked by grep before a change.
const UNUSED_BOOTSTRAP = /^(carousel|accordion|spinner-|tooltip|bs-tooltip|list-group|breadcrumb|navbar|nav-tabs|nav-pills|nav-underline|nav-fill|nav-justified|input-group|form-floating|form-range|card|progress|placeholder|ratio|vr$|figure|blockquote|display-\d|text-bg-|link-|toast|lead$|badge)/;
function prunedBootstrap() {
    const target = fileURLToPath(new URL('./node_modules/.ptm/bootstrap.pruned.css', import.meta.url));
    return {
        name: 'ptm-pruned-bootstrap',
        config() {
            const needsUnused = (selector) => selector.some((c) => c.type === 'class' && UNUSED_BOOTSTRAP.test(c.name));
            const { code } = transform({ filename: 'bootstrap.min.css', code: readFileSync(fileURLToPath(new URL('./node_modules/bootstrap/dist/css/bootstrap.min.css', import.meta.url))), minify: true,
                visitor: { Rule: { style(rule) {
                    const keep = rule.value.selectors.filter((s) => !needsUnused(s));
                    if (keep.length === rule.value.selectors.length) return undefined;
                    return keep.length ? { ...rule, value: { ...rule.value, selectors: keep } } : [];
                } } } });
            mkdirSync(dirname(target), { recursive: true });
            writeFileSync(target, code);
            return { resolve: { alias: [{ find: /^bootstrap\/dist\/css\/bootstrap\.min\.css$/, replacement: target }] } };
        },
    };
}

// Icon sprite: every resources/icons/*.svg becomes one <symbol> in public/build/icons-<hash>.svg, and the icon component points at
// it with <use>, so a page with hundreds of icons (a dense Kanban board) carries a short reference per icon instead of the full
// drawing. public/build/icons.json names the current file; without it the component falls back to inline SVG.
function iconSprite() {
    const write = () => {
        const symbols = readdirSync('resources/icons').filter((f) => f.endsWith('.svg')).sort().map((f) => {
            const svg = readFileSync(`resources/icons/${f}`, 'utf8');
            const inner = svg.replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').trim();
            return `<symbol id="i-${f.slice(0, -4)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${inner}</symbol>`;
        }).join('');
        const sprite = `<svg xmlns="http://www.w3.org/2000/svg">${symbols}</svg>`;
        const file = `icons-${createHash('sha256').update(sprite).digest('hex').slice(0, 12)}.svg`;
        mkdirSync('public/build', { recursive: true });
        writeFileSync(`public/build/${file}`, sprite);
        writeFileSync('public/build/icons.json', JSON.stringify({ file }));
    };
    return { name: 'ptm-icon-sprite', configureServer: write, closeBundle: write };
}


// Geist Sans and Geist Mono (SIL OFL 1.1) are self-hosted: downloaded at build time and served by the app, no third-party font host
// at run time. Only the two weights above the fold are preloaded; metric-matched fallbacks avoid layout shift while they load.
export default defineConfig({
    plugins: [
        iconSprite(),
        prunedBootstrap(),
        laravel({
            input: ['resources/css/layout.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Geist', { alias: 'sans', weights: [400, 500, 600], preload: [{ weight: 400 }, { weight: 500 }], fallbacks: ['system-ui', 'sans-serif'] }),
                bunny('Geist Mono', { alias: 'mono', weights: [400, 500], preload: false, fallbacks: ['ui-monospace', 'monospace'] }),
            ],
        }),
    ],
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});
