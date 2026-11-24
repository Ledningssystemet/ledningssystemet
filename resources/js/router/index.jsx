import React, { useEffect } from 'react';
import axios from 'axios';
import {
    createBrowserRouter, isRouteErrorResponse, Outlet, RouterProvider,
    useLocation, useNavigation, useRouteError,
} from 'react-router-dom';
import { t } from '../lib/i18n';

const pagePaths = new Set(['/app/projects', '/app/ghgcalculator']);

async function loadPermissions({ request }) {
    try {
        const response = await axios.get('/api/v1/permissions', {
            signal: request.signal,
            headers: { Accept: 'application/json' },
        });
        const data = response.data;
        if (!Array.isArray(data?.permissions) || !Number.isSafeInteger(data?.currentUserId)
            || typeof data?.pages?.projects?.canCreate !== 'boolean') {
            throw new Error(t('The page could not be loaded.'));
        }
        return data;
    } catch (error) {
        if (axios.isAxiosError(error) && error.response) {
            throw new Response(error.response.data?.message || t('The page could not be loaded.'), {
                status: error.response.status,
            });
        }
        throw error;
    }
}

function PageError() {
    const error = useRouteError();
    return <div className="alert alert-danger" role="alert">
        <h2>{t('The page could not be loaded.')}</h2>
        <p>{isRouteErrorResponse(error)
            ? `${error.status}: ${error.data}`
            : error instanceof Error ? error.message : t('An unexpected error occurred.')}</p>
        <a className="btn btn-outline-primary" href={window.location.href}>{t('Retry')}</a>
    </div>;
}

function AppLayout() {
    const location = useLocation();
    const navigation = useNavigation();

    useEffect(() => {
        document.querySelectorAll('.sidebar .nav-link:not(.nav-group-toggle)').forEach(link => {
            const active = new URL(link.href, window.location.href).pathname.replace(/\/$/, '') === location.pathname.replace(/\/$/, '');
            link.classList.toggle('active', active);
            if (active) {
                const group = link.closest('.nav-group');
                group?.querySelector('.nav-group-toggle')?.classList.remove('collapsed');
                group?.querySelector('.nav-group-items')?.classList.add('show');
            }
        });
    }, [location.pathname]);

    return <div aria-busy={navigation.state !== 'idle'}>
        {navigation.state !== 'idle' && <p role="status">{t('Loading data…')}</p>}
        <Outlet />
    </div>;
}

function LoadingPage() {
    return <p role="status">{t('Loading data…')}</p>;
}

function createAppRouter() {
    return createBrowserRouter([{
        id: 'app-root',
        Component: AppLayout,
        loader: loadPermissions,
        shouldRevalidate: () => false,
        ErrorBoundary: PageError,
        HydrateFallback: LoadingPage,
        children: [
            {
                path: 'projects',
                lazy: () => import('./ProjectsPage.jsx'),
                ErrorBoundary: PageError,
            },
            {
                path: 'ghgcalculator',
                lazy: () => import('./GhgCalculatorPage.jsx'),
                ErrorBoundary: PageError,
            },
            {
                path: '*',
                loader: () => { throw new Response(t('Not found'), { status: 404 }); },
                ErrorBoundary: PageError,
            },
        ],
    }], { basename: '/app' });
}

const router = createAppRouter();

export default function AppRouter() {
    useEffect(() => {
        const navigate = event => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin || !pagePaths.has(url.pathname.replace(/\/$/, ''))) return;
            event.preventDefault();
            void router.navigate(`${url.pathname.slice('/app'.length)}${url.search}${url.hash}`);
        };
        document.addEventListener('click', navigate);
        return () => document.removeEventListener('click', navigate);
    }, [router]);

    return <RouterProvider router={router} />;
}
