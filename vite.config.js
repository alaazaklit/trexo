import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/driver-simulator.css',
                'resources/css/dashboard.css',
                'resources/css/public-ltr.css',
                'resources/css/public-rtl.css',
                'resources/js/app.js',
                'resources/js/driver-simulator.js',
                'resources/js/dashboard.js',
                'resources/js/public.js',
            ],
            refresh: true,
        }),
    ],
});
