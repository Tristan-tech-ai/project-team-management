import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

// Inter di-host sendiri (diunduh saat build, Petunjuk Teknis 6.1: Inter / system sans).
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/tata-letak.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Inter', { alias: 'sans', weights: [400, 500, 600, 700], fallbacks: ['system-ui', 'sans-serif'] }),
            ],
        }),
    ],
    server: { watch: { ignored: ['**/storage/framework/views/**'] } },
});
