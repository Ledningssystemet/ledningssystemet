import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
   plugins: [
      laravel({
         input: [
            'resources/js/apps/core/loadstyles.js',
            'resources/js/apps/core/app.js',
            'resources/js/bpmneditor/index.js',
            'resources/js/documenteditor/index.js',
            'resources/js/apps/core/componentRegistry.jsx',
            'resources/js/apps/core/bootstrap.jsx',
         ],
         refresh: true,
      }),
   ],
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
