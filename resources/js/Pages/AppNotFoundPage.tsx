import AppLayout from '@/Layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { APP_DASHBOARD_PATH, APP_HOME_PATH } from '@/app/routes';
import { Link } from 'react-router-dom';

export default function AppNotFoundPage() {
    const { t } = useTranslations();

    return (
        <AppLayout>
            <div className="flex min-h-[60vh] items-center justify-center px-4">
                <div className="w-full max-w-3xl rounded-2xl border border-border bg-card p-8 shadow-sm">
                    <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                        <h1 className="border-b border-border pb-4 text-4xl font-semibold tracking-tight text-foreground sm:border-b-0 sm:border-r sm:pb-0 sm:pr-4">
                            404
                        </h1>

                        <div className="sm:pl-4">
                            <h2 className="text-2xl font-semibold text-foreground">
                                {t('ui.app.not_found_title')}
                            </h2>
                            <p className="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">
                                {t('ui.app.not_found_description')}
                            </p>

                            <div className="mt-5 flex flex-wrap gap-2">
                                <Link
                                    to={APP_HOME_PATH}
                                    className="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                                >
                                    {t('ui.app.go_to_home')}
                                </Link>
                                <Link
                                    to={APP_DASHBOARD_PATH}
                                    className="inline-flex items-center rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                                >
                                    {t('ui.app.go_to_dashboard')}
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

