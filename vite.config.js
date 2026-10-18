import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/datatable.js', 'resources/js/driver-form.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
