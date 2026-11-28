import {defineConfig} from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
   plugins: [
      laravel({
         input: [
            'resources/js/components/core/loadstyles.js',
            'resources/js/components/core/app.js',
            'resources/js/bpmneditor/index.js',
            'resources/js/documenteditor/index.js',
            'resources/js/components/core/componentRegistry.jsx',
            'resources/js/components/core/bootstrap.jsx',
            'resources/js/apps/systemadmin/index.jsx',
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
