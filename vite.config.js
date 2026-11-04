import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Inter', {
                    weights: [400, 500, 600],
                    subsets: ['latin', 'latin-ext', 'cyrillic'],
                }),
                bunny('Manrope', {
                    weights: [600, 700, 800],
                    subsets: ['latin', 'latin-ext', 'cyrillic'],
                }),
                bunny('IBM Plex Mono', {
                    weights: [400, 500],
                    subsets: ['latin', 'latin-ext', 'cyrillic'],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
