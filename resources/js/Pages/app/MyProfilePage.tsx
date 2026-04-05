import { useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { APP_HOME_PATH } from '@/app/routes';
import { Link } from 'react-router-dom';
import { UserCircle, Mail, Shield, ChevronRight } from 'lucide-react';
import type { AppSectionRoute } from '@/app/routes';

interface MyProfilePageProps {
    route: AppSectionRoute;
}

export default function MyProfilePage({ route }: MyProfilePageProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.my_profile.title') });
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
                    <span>{t('pages.my_profile.title')}</span>
                </nav>

                {/* Page header */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
                            <UserCircle className="h-9 w-9 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.my_profile.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.my_profile.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Personal information */}
                    <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-foreground">
                            {t('pages.my_profile.section_personal_info')}
                        </h2>
                        <ul className="mt-4 space-y-4">
                            <li className="flex items-center gap-3 rounded-xl bg-muted/50 px-4 py-3">
                                <UserCircle className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {t('pages.my_profile.name_label')}
                                    </p>
                                    <p className="mt-0.5 text-sm font-medium text-foreground">
                                        {t('ui.nav.profile_name_placeholder')}
                                    </p>
                                </div>
                            </li>
                            <li className="flex items-center gap-3 rounded-xl bg-muted/50 px-4 py-3">
                                <Mail className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {t('pages.my_profile.email_label')}
                                    </p>
                                    <p className="mt-0.5 text-sm font-medium text-foreground">—</p>
                                </div>
                            </li>
                            <li className="flex items-center gap-3 rounded-xl bg-muted/50 px-4 py-3">
                                <Shield className="h-4 w-4 shrink-0 text-muted-foreground" />
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {t('pages.my_profile.role_label')}
                                    </p>
                                    <p className="mt-0.5 text-sm font-medium text-foreground">—</p>
                                </div>
                            </li>
                        </ul>
                    </section>

                    {/* Account settings */}
                    <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <h2 className="text-base font-semibold text-foreground">
                            {t('pages.my_profile.section_account')}
                        </h2>
                        <div className="mt-4 flex flex-col gap-3">
                            <button
                                type="button"
                                className="flex w-full items-center justify-between rounded-xl bg-muted/50 px-4 py-3 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                            >
                                {t('pages.my_profile.edit_profile')}
                                <ChevronRight className="h-4 w-4 text-muted-foreground" />
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

