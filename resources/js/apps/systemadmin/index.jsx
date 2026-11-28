import React, { useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import PageLoading from '../../components/pageloading';
import * as Dialog from '@radix-ui/react-dialog';
import {
    createBrowserRouter, isRouteErrorResponse, Outlet, redirect, RouterProvider, useLocation, useNavigation, useRouteError,
} from 'react-router-dom';
import { t } from '../../lib/i18n';
import { createHydrationStore } from './hydration';
import { HydrationProvider, useSystemAdmin } from './HydrationProvider';
import ErrorPage from '../../components/errorpage';
import '../../components/ui/ui.css';

const bootstrap = JSON.parse(document.querySelector('systemadmin-app').dataset.props);
const hydration = createHydrationStore(bootstrap);
const pagePaths = new Set([
    '/systemadmin/users',
    '/systemadmin/access',
    '/systemadmin/settings',
    '/systemadmin/apitokens',
    '/systemadmin/swagger',
]);

async function loadPage({ request }) {
    await hydration.load(request.signal);
    return null;
}

function PageError() {
    const error = useRouteError();
    const notFound = isRouteErrorResponse(error) && error.status === 404;
    const message = isRouteErrorResponse(error)
        ? `${error.status}: ${error.data}`
        : error instanceof Error ? error.message : t('An unexpected error occurred.');

    return <ErrorPage
        errorType={notFound ? 'pageNotFound' : 'pageLoadError'}
        title={notFound ? t('Page not found') : t('The page could not be loaded.')}
        text={notFound ? t('The link may be incorrect or the page may have moved.') : message}
        homeLabel={t('Go to homepage')}
        retryLabel={t('Retry')}
    />;
}

function Layout() {
    return <HydrationProvider store={hydration} csrfToken={bootstrap.csrfToken}>
        <HydratedLayout />
    </HydrationProvider>;
}

function HydratedLayout() {
    const location = useLocation();
    const navigation = useNavigation();
    const { data, refreshing } = useSystemAdmin();

    useEffect(() => {
        const pathname = location.pathname.startsWith('/systemadmin')
            ? location.pathname
            : `/systemadmin${location.pathname}`;
        document.querySelectorAll('.sidebar .nav-link:not(.nav-group-toggle)').forEach(link => {
            const active = new URL(link.href, window.location.href).pathname.replace(/\/$/, '') === pathname.replace(/\/$/, '');
            link.classList.toggle('active', active);
            if (active) {
                const group = link.closest('.nav-group');
                group?.querySelector('.nav-group-toggle')?.classList.remove('collapsed');
                group?.querySelector('.nav-group-items')?.classList.add('show');
            }
        });
    }, [location.pathname]);

    const loading = navigation.state !== 'idle' || refreshing;

    return <div aria-busy={loading}>
        {loading && <PageLoading />}
        {data && <Outlet />}
    </div>;
}

function createSystemAdminRouter() {
    return createBrowserRouter([{
        id: 'systemadmin-root',
        Component: Layout,
        loader: loadPage,
        shouldRevalidate: ({ currentUrl, nextUrl, defaultShouldRevalidate }) =>
            currentUrl.pathname !== nextUrl.pathname || defaultShouldRevalidate,
        ErrorBoundary: PageError,
        HydrateFallback: PageLoading,
        children: [
            { index: true, loader: () => redirect('/systemadmin/users') },
            { path: 'users', lazy: () => import('./UsersPage.jsx'), ErrorBoundary: PageError },
            { path: 'access', lazy: () => import('./AccessGroupsPage.jsx'), ErrorBoundary: PageError },
            { path: 'settings', lazy: () => import('./SettingsPage.jsx'), ErrorBoundary: PageError },
            { path: 'apitokens', lazy: () => import('./ApiTokensPage.jsx'), ErrorBoundary: PageError },
            { path: 'swagger', lazy: () => import('./SwaggerPage.jsx'), ErrorBoundary: PageError },
            {
                path: '*',
                loader: () => { throw new Response(t('Not found'), { status: 404 }); },
                ErrorBoundary: PageError,
            },
        ],
    }], { basename: '/systemadmin' });
}

const router = createSystemAdminRouter();

export default function SystemAdminApp() {
    useEffect(() => {
        const navigate = event => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
            const url = new URL(link.href, window.location.href);
            if (url.origin !== window.location.origin
                || (!pagePaths.has(url.pathname.replace(/\/$/, '')) && url.pathname !== '/systemadmin')) return;
            event.preventDefault();
            void router.navigate(`${url.pathname.slice('/systemadmin'.length)}${url.search}${url.hash}`);
        };
        document.addEventListener('click', navigate);
        return () => document.removeEventListener('click', navigate);
    }, []);

    return <RouterProvider router={router} />;
}

const mountElement = document.querySelector('systemadmin-app');

if (mountElement) {
    createRoot(mountElement).render(
        <React.StrictMode>
            <SystemAdminApp />
        </React.StrictMode>
    );
}
