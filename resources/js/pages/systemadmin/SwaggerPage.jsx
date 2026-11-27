import React, { useEffect, useRef } from 'react';
import { useRouteLoaderData } from 'react-router-dom';
import { t } from '../../lib/i18n';

let swaggerAssets;

function loadSwaggerAssets() {
    if (window.SwaggerUIBundle) return Promise.resolve();
    if (!swaggerAssets) {
        swaggerAssets = Promise.all([
            '/vendors/swagger/swagger-ui-bundle.js',
            '/vendors/swagger/swagger-ui-standalone-preset.js',
        ].map(src => new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.crossOrigin = 'anonymous';
            script.addEventListener('load', resolve, { once: true });
            script.addEventListener('error', reject, { once: true });
            document.body.appendChild(script);
        }))).catch(error => {
            swaggerAssets = null;
            throw error;
        });
    }

    return swaggerAssets;
}

export function Component() {
    const { specUrl } = useRouteLoaderData('systemadmin-root');
    const container = useRef(null);

    useEffect(() => {
        const stylesheet = document.createElement('link');
        stylesheet.rel = 'stylesheet';
        stylesheet.href = '/vendors/swagger/swagger-ui.css';
        document.head.appendChild(stylesheet);

        let cancelled = false;
        loadSwaggerAssets().then(() => {
            if (!cancelled && window.SwaggerUIBundle) {
                window.SwaggerUIBundle({ url: specUrl, dom_id: '#swaggerContainer' });
            }
        }).catch(() => {
            if (!cancelled && container.current) {
                container.current.textContent = t('The page could not be loaded.');
            }
        });

        return () => {
            cancelled = true;
            stylesheet.remove();
        };
    }, [specUrl]);

    return <div ref={container} id="swaggerContainer" />;
}
