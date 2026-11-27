import React, { useEffect } from 'react';
import {
    createBrowserRouter, isRouteErrorResponse, Outlet, redirect, RouterProvider,
    useLocation, useNavigation, useRouteError,
} from 'react-router-dom';
import { t } from '../../lib/i18n';

let initialPageProps = document.querySelector('systemadmin-app')?.dataset.props;
const pagePaths = new Set([
    '/systemadmin/users',
    '/systemadmin/access',
    '/systemadmin/settings',
    '/systemadmin/apitokens',
    '/systemadmin/swagger',
]);

function readPageProps(html) {
    const element = new DOMParser().parseFromString(html, 'text/html').querySelector('systemadmin-app');
    if (!element?.dataset.props) {
        throw new Error(t('The page could not be loaded.'));
    }

    return JSON.parse(element.dataset.props);
}

async function loadPage({ request }) {
    if (initialPageProps) {
        const props = JSON.parse(initialPageProps);
        initialPageProps = null;
        return props;
    }

    const response = await fetch(request.url, {
        signal: request.signal,
        credentials: 'same-origin',
        headers: { Accept: 'text/html' },
    });
    if (!response.ok) {
        throw new Response(t('The page could not be loaded.'), { status: response.status });
    }

    return readPageProps(await response.text());
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

function Layout() {
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

function createSystemAdminRouter() {
    return createBrowserRouter([{
        id: 'systemadmin-root',
        Component: Layout,
        loader: loadPage,
        shouldRevalidate: ({ currentUrl, nextUrl, defaultShouldRevalidate }) =>
            currentUrl.pathname !== nextUrl.pathname || defaultShouldRevalidate,
        ErrorBoundary: PageError,
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
