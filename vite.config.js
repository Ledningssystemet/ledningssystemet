import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/loadstyles.js',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    resolve: {
        alias: {
            '@components': path.resolve(__dirname, 'resources/js/components'),
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    base: './',
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            protocol: 'ws',
            host: 'localhost',
        },
    },
});
