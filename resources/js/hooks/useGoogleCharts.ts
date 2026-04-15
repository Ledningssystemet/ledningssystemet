import { useEffect, useState } from 'react';

const GOOGLE_CHARTS_SCRIPT_ID = 'google-charts-loader';

export function useGoogleCharts() {
    const [loaded, setLoaded] = useState(false);

    useEffect(() => {
        if (typeof window === 'undefined') {
            return;
        }

        const markLoaded = () => setLoaded(true);

        if (window.google?.charts?.load && window.google?.visualization) {
            markLoaded();
            return;
        }

        const existingScript = document.getElementById(GOOGLE_CHARTS_SCRIPT_ID) as HTMLScriptElement | null;

        const initializeCharts = () => {
            if (!window.google?.charts?.load) {
                return;
            }

            window.google.charts.load('current', { packages: ['corechart'] });
            window.google.charts.setOnLoadCallback(markLoaded);
        };

        if (existingScript) {
            if (existingScript.dataset.loaded === 'true') {
                initializeCharts();
            } else {
                existingScript.addEventListener('load', initializeCharts, { once: true });
            }

            return;
        }

        const script = document.createElement('script');
        script.id = GOOGLE_CHARTS_SCRIPT_ID;
        script.src = 'https://www.gstatic.com/charts/loader.js';
        script.async = true;
        script.addEventListener(
            'load',
            () => {
                script.dataset.loaded = 'true';
                initializeCharts();
            },
            { once: true }
        );

        document.head.appendChild(script);
    }, []);

    return loaded;
}

