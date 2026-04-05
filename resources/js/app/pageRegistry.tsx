import React, {JSX, lazy} from 'react';
import AppSectionPage from '@/Pages/AppSectionPage';
import type { AppSectionRoute } from '@/app/routes';

// Lazy load page components - only import pages that are created
const pageComponents: Record<string, React.LazyExoticComponent<({ route }: { route: AppSectionRoute }) => JSX.Element>> = {
    observation: lazy(() => import('@/Pages/app/ObservationPage')),
    settings: lazy(() => import('@/Pages/app/SettingsPage')),
    help: lazy(() => import('@/Pages/app/HelpPage')),
    'min-profil': lazy(() => import('@/Pages/app/MinProfilPage')),
};

export function resolveAppRouteElement(route: AppSectionRoute) {
    const PageComponent = pageComponents[route.key];

    if (!PageComponent) {
        // Fallback for unmapped routes
        return (
            <AppSectionPage
                title={route.label}
                description={route.description}
                categoryLabel={route.categoryLabel}
                sectionLabel={route.sectionLabel}
                routeKey={route.key}
            />
        );
    }

    return <PageComponent route={route} />;
}



