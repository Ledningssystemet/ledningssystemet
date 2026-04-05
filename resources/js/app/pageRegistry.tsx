import React, { JSX, lazy } from 'react';
import type { AppSectionRoute } from '@/app/routes';

// Lazy load page components – one file per route key
const pageComponents: Record<string, React.LazyExoticComponent<({ route }: { route: AppSectionRoute }) => JSX.Element>> = {
    'my-profile': lazy(() => import('@/Pages/app/MyProfilePage')),
    'my-tasks': lazy(() => import('@/Pages/app/MyTasksPage')),
    'my-documents': lazy(() => import('@/Pages/app/MyDocumentsPage')),
    customers: lazy(() => import('@/Pages/app/CustomersPage')),
    settings: lazy(() => import('@/Pages/app/SettingsPage')),
};

export function resolveAppRouteElement(route: AppSectionRoute) {
    const PageComponent = pageComponents[route.key];

    if (!PageComponent) {
        // No dedicated page exists for this route key yet
        return null;
    }

    return <PageComponent route={route} />;
}
