import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import AppLayout from '@/layouts/AppLayout';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface AppSectionPlaceholderPageProps {
    route: AppSectionRoute;
}

export default function AppSectionPlaceholderPage({ route }: AppSectionPlaceholderPageProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: route.label });
        return () => {
            document.title = previousTitle;
        };
    }, [route.label, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{route.label}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <h1 className="text-2xl font-semibold tracking-tight text-foreground">{route.label}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {route.description ?? t('pages.settings.coming_soon')}
                    </p>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <p className="text-sm text-muted-foreground">{t('pages.settings.coming_soon')}</p>
                </section>
            </div>
        </AppLayout>
    );
}

