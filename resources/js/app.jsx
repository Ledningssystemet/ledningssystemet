import './bootstrap';
import '../css/app.css';
import React from 'react';
import { createRoot } from 'react-dom/client';
import { createInertiaApp } from '@inertiajs/react';
import { StrictMode } from 'react';
import { initializeFrontendPlugins } from '@/plugins/runtime';

const pages = {
    ...import.meta.glob('./pages/**/*.{jsx,tsx}'),
    ...import.meta.glob('./Pages/**/*.{jsx,tsx}'),
};

window.React = React;

async function bootstrapApplication() {
    await initializeFrontendPlugins();

    await createInertiaApp({
        resolve: async (name) => {
            const page =
                pages[`./pages/${name}.tsx`] ??
                pages[`./pages/${name}.jsx`] ??
                pages[`./Pages/${name}.tsx`] ??
                pages[`./Pages/${name}.jsx`];

            if (!page) {
                throw new Error(`Unknown Inertia page: ${name}`);
            }

            const module = await page();
            return module.default;
        },
        setup({ el, App, props }) {
            createRoot(el).render(<StrictMode><App {...props} /></StrictMode>);
        },
    });
}

bootstrapApplication();
