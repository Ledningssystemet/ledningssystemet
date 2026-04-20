import React, { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { APP_HOME_PATH } from '@/app/routes';
import { PluginSlot } from '@/components/plugins/PluginSlot';
import { useTranslations } from '@/hooks/useTranslations';
import { getPluginSlotContributions } from '@/plugins/runtime';
import type { AppSectionRoute } from '@/app/routes';

interface PageHeaderProps {
    title: string;
    description?: string;
    icon: React.ReactNode;
    route?: AppSectionRoute;
    /** Extra content rendered to the right of the title/icon (e.g. export buttons) */
    actions?: React.ReactNode;
}

/**
 * Standard page header used across all CRUD pages.
 * Renders the breadcrumb, sets the document title and shows the icon + title card.
 */
export function PageHeader({ title, description, icon, route, actions }: PageHeaderProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: title });
        return () => {
            document.title = previousTitle;
        };
    }, [title, t]);

    const resolvedDescription = route?.description ?? description;
    const hasGlobalPluginActions = getPluginSlotContributions('page.header.actions').length > 0;
    const routeSlotName = route ? `page.header.actions.${route.key}` : null;
    const hasRoutePluginActions = routeSlotName ? getPluginSlotContributions(routeSlotName).length > 0 : false;
    const hasActions = Boolean(actions) || hasGlobalPluginActions || hasRoutePluginActions;
    const slotContext = {
        route,
        title,
        description: resolvedDescription,
    };

    return (
        <>
            <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                    {t('ui.app.breadcrumb_home')}
                </Link>
                <span>/</span>
                <span>{title}</span>
            </nav>

            <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            {icon}
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {title}
                            </h1>
                            {resolvedDescription && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {resolvedDescription}
                                </p>
                            )}
                        </div>
                    </div>
                    {hasActions && (
                        <div className="flex shrink-0 items-center gap-2">
                            {actions}
                            <PluginSlot name="page.header.actions" context={slotContext} />
                            {routeSlotName && <PluginSlot name={routeSlotName} context={slotContext} />}
                        </div>
                    )}
                </div>
            </section>
        </>
    );
}

