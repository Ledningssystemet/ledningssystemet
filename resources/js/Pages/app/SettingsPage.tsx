import { useEffect } from 'react';
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { APP_HOME_PATH } from '@/app/routes';
import { Link } from 'react-router-dom';
import { Settings } from 'lucide-react';
import type { AppSectionRoute } from '@/app/routes';

interface SettingsPageProps {
    route: AppSectionRoute;
}

export default function SettingsPage({ route }: SettingsPageProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.settings.title') });
        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                {/* Breadcrumb */}
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.settings.title')}</span>
                </nav>

                {/* Page header */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Settings className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.settings.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.settings.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* General */}
                    <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-foreground">
                            {t('pages.settings.section_general')}
                        </h2>
                        <div className="mt-4 space-y-4">
                            <div className="rounded-xl bg-muted/50 px-4 py-3">
                                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {t('pages.settings.language_label')}
                                </p>
                                <p className="mt-1 text-sm font-medium text-foreground">—</p>
                            </div>
                            <div className="rounded-xl bg-muted/50 px-4 py-3">
                                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {t('pages.settings.theme_label')}
                                </p>
                                <p className="mt-1 text-sm font-medium text-foreground">—</p>
                            </div>
                        </div>
                    </section>

                    {/* Notifications */}
                    <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-foreground">
                            {t('pages.settings.section_notifications')}
                        </h2>
                        <div className="mt-4 rounded-xl bg-muted/50 px-4 py-3">
                            <p className="text-sm text-muted-foreground">{t('pages.settings.coming_soon')}</p>
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
