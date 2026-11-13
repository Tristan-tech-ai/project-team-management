import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

// Inter is self-hosted (downloaded at build time, Technical Guide 6.1: Inter / system sans).
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/layout.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Inter', { alias: 'sans', weights: [400, 500, 600, 700], fallbacks: ['system-ui', 'sans-serif'] }),
            ],
        }),
    ],
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});
