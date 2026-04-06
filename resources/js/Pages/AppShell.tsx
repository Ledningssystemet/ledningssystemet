import { useMemo, Suspense } from 'react';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import {
    APP_HOME_PATH,
    APP_SETTINGS_PATH,
    buildMenuRoutes,
} from '@/app/routes';
import { resolveAppRouteElement } from '@/app/pageRegistry';
import { useMenuData } from '@/hooks/useMenuData';
import AppNotFoundPage from './AppNotFoundPage';
import UserDashboard from './UserDashboard';

function PageLoader() {
    return (
        <div className="flex items-center justify-center min-h-screen">
            <div className="text-center">
                <div className="h-8 w-8 animate-spin rounded-full border-4 border-muted border-t-primary mx-auto mb-4"></div>
                <p className="text-sm text-muted-foreground">Laddar...</p>
            </div>
        </div>
    );
}

export default function AppShell() {
    const categories = useMenuData();
    const menuRoutes = useMemo(() => buildMenuRoutes(categories), [categories]);

    const utilityRoutes = [
        { key: 'settings', path: APP_SETTINGS_PATH },
    ];

    return (
        <BrowserRouter basename="/app">
            <Suspense fallback={<PageLoader />}>
                <Routes>
                    <Route path={APP_HOME_PATH} element={<UserDashboard />} />
                    {utilityRoutes.map((route) => {
                        const element = resolveAppRouteElement({
                            key: route.key,
                            path: route.path,
                            label: route.key.charAt(0).toUpperCase() + route.key.slice(1),
                        });
                        return (
                            <Route
                                key={route.key}
                                path={route.path}
                                element={
                                    <Suspense fallback={<PageLoader />}>
                                        {element ?? <AppNotFoundPage />}
                                    </Suspense>
                                }
                            />
                        );
                    })}
                    {menuRoutes.map((route) => {
                        const element = resolveAppRouteElement(route);
                        return (
                            <Route
                                key={route.path}
                                path={route.path}
                                element={
                                    <Suspense fallback={<PageLoader />}>
                                        {element ?? <AppNotFoundPage />}
                                    </Suspense>
                                }
                            />
                        );
                    })}
                    <Route path="*" element={<AppNotFoundPage />} />
                </Routes>
            </Suspense>
        </BrowserRouter>
    );
}

