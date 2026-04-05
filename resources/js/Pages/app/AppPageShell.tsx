import AppLayout from '@/Layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { APP_DASHBOARD_PATH, APP_HOME_PATH } from '@/app/routes';
import { Link, useLocation } from 'react-router-dom';
import { ReactNode, useEffect, useMemo } from 'react';

interface BreadcrumbItem {
    label: string;
    to?: string;
}

interface AppPageShellProps {
    title: string;
    description?: string;
    categoryLabel?: string;
    sectionLabel?: string;
    toneLabel?: string;
    routeKey?: string;
    summary: string;
    highlightsTitle: string;
    highlights: string[];
    nextStepsTitle: string;
    nextSteps: string[];
    aside?: ReactNode;
}

export default function AppPageShell({
    title,
    description,
    categoryLabel,
    sectionLabel,
    toneLabel,
    routeKey,
    summary,
    highlightsTitle,
    highlights,
    nextStepsTitle,
    nextSteps,
    aside,
}: AppPageShellProps) {
    const { t } = useTranslations();
    const location = useLocation();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: title });

        return () => {
            document.title = previousTitle;
        };
    }, [t, title]);

    const breadcrumbs = useMemo<BreadcrumbItem[]>(() => {
        const items: BreadcrumbItem[] = [
            { label: t('ui.app.breadcrumb_home'), to: APP_HOME_PATH },
        ];

        if (categoryLabel) {
            items.push({ label: categoryLabel });
        }

        items.push({ label: title });

        return items;
    }, [categoryLabel, t, title]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                    {breadcrumbs.map((item, index) => (
                        <div key={`${item.label}-${index}`} className="flex items-center gap-2">
                            {item.to ? (
                                <Link to={item.to} className="transition-colors hover:text-foreground">
                                    {item.label}
                                </Link>
                            ) : (
                                <span>{item.label}</span>
                            )}
                            {index < breadcrumbs.length - 1 && <span>/</span>}
                        </div>
                    ))}
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div className="space-y-3">
                            <div className="flex flex-wrap items-center gap-2 text-xs font-medium uppercase tracking-[0.2em] text-muted-foreground">
                                {categoryLabel && (
                                    <span className="rounded-full bg-muted px-2.5 py-1">
                                        {categoryLabel}
                                    </span>
                                )}
                                {sectionLabel && (
                                    <span className="rounded-full bg-muted px-2.5 py-1">
                                        {sectionLabel}
                                    </span>
                                )}
                                {toneLabel && (
                                    <span className="rounded-full bg-primary/10 px-2.5 py-1 text-primary">
                                        {toneLabel}
                                    </span>
                                )}
                            </div>

                            <div>
                                <h1 className="text-3xl font-semibold tracking-tight text-foreground">{title}</h1>
                                <p className="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                                    {description ?? summary}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
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
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                    <div className="space-y-6">
                        <article className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            <h2 className="text-lg font-semibold text-foreground">{t('ui.app.workspace_title')}</h2>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">{summary}</p>
                        </article>

                        <article className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            <h2 className="text-lg font-semibold text-foreground">{highlightsTitle}</h2>
                            <ul className="mt-4 space-y-3">
                                {highlights.map((item) => (
                                    <li key={item} className="rounded-xl bg-muted/50 px-4 py-3 text-sm leading-6 text-foreground">
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </article>
                    </div>

                    <aside className="space-y-6">
                        <article className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            <h2 className="text-lg font-semibold text-foreground">{nextStepsTitle}</h2>
                            <ul className="mt-4 space-y-3">
                                {nextSteps.map((item) => (
                                    <li key={item} className="rounded-xl bg-muted/50 px-4 py-3 text-sm leading-6 text-foreground">
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </article>

                        <article className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                            <h2 className="text-lg font-semibold text-foreground">{t('ui.app.route_context_title')}</h2>
                            <div className="mt-4 space-y-4 text-sm text-muted-foreground">
                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                        {t('ui.app.current_path')}
                                    </p>
                                    <p className="mt-1 break-all font-medium text-foreground">{location.pathname}</p>
                                </div>

                                {routeKey && (
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {t('ui.app.route_key')}
                                        </p>
                                        <p className="mt-1 font-medium text-foreground">{routeKey}</p>
                                    </div>
                                )}

                                {categoryLabel && (
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {t('ui.app.category_label')}
                                        </p>
                                        <p className="mt-1 font-medium text-foreground">{categoryLabel}</p>
                                    </div>
                                )}

                                {sectionLabel && (
                                    <div>
                                        <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                            {t('ui.app.section_label')}
                                        </p>
                                        <p className="mt-1 font-medium text-foreground">{sectionLabel}</p>
                                    </div>
                                )}
                            </div>
                        </article>

                        {aside}
                    </aside>
                </section>
            </div>
        </AppLayout>
    );
}

