import React, { JSX, lazy } from 'react';
import type { AppSectionRoute } from '@/app/routes';

// Lazy load page components – one file per route key
const pageComponents: Record<string, React.LazyExoticComponent<({ route }: { route: AppSectionRoute }) => JSX.Element>> = {
    'my-profile': lazy(() => import('@/pages/app/MyProfilePage')),
    'my-tasks': lazy(() => import('@/pages/app/MyTasksPage')),
    'my-documents': lazy(() => import('@/pages/app/MyDocumentsPage')),
    customers: lazy(() => import('@/pages/app/CustomersPage')),
};

export function resolveAppRouteElement(route: AppSectionRoute) {
    const PageComponent = pageComponents[route.key];

    if (!PageComponent) {
        // No dedicated page exists for this route key yet
        return null;
    }

    return <PageComponent route={route} />;
}
