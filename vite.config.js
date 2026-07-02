import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'path';

export default defineConfig({
    plugins: [
        react(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx', 'resources/js/swagger-docs.ts'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
            '@/Components': path.resolve(__dirname, './resources/js/Components'),
            '@/Pages': path.resolve(__dirname, './resources/js/Pages'),
            '@/Layouts': path.resolve(__dirname, './resources/js/Layouts'),
            '@/hooks': path.resolve(__dirname, './resources/js/hooks'),
            '@/Lib': path.resolve(__dirname, './resources/js/Lib'),
            '@/Assets': path.resolve(__dirname, './resources/js/Assets'),
            '@/types': path.resolve(__dirname, './resources/js/types'),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        chunkSizeWarningLimit: 2000,
    }
});
