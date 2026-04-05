import { useEffect, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { APP_HOME_PATH } from '@/app/routes';
import { Link } from 'react-router-dom';
import { ClipboardList } from 'lucide-react';
import { cn } from '@/Lib/utils';
import type { AppSectionRoute } from '@/app/routes';

interface MyTasksPageProps {
    route: AppSectionRoute;
}

type FilterKey = 'all' | 'open' | 'done' | 'overdue';

export default function MyTasksPage({ route }: MyTasksPageProps) {
    const { t } = useTranslations();
    const [activeFilter, setActiveFilter] = useState<FilterKey>('all');

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.my_tasks.title') });
        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const filters: { key: FilterKey; label: string }[] = [
        { key: 'all', label: t('pages.my_tasks.filter_all') },
        { key: 'open', label: t('pages.my_tasks.filter_open') },
        { key: 'done', label: t('pages.my_tasks.filter_done') },
        { key: 'overdue', label: t('pages.my_tasks.filter_overdue') },
    ];

    return (
        <AppLayout>
            <div className="space-y-6">
                {/* Breadcrumb */}
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.my_tasks.title')}</span>
                </nav>

                {/* Page header */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                                <ClipboardList className="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                    {t('pages.my_tasks.title')}
                                </h1>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {route.description ?? t('pages.my_tasks.description')}
                                </p>
                            </div>
                        </div>

                        {/* Summary counters */}
                        <div className="flex gap-3">
                            <div className="rounded-xl border border-border bg-muted/50 px-4 py-2 text-center">
                                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    {t('pages.my_tasks.open_tasks')}
                                </p>
                                <p className="mt-1 text-2xl font-bold text-foreground">0</p>
                            </div>
                            <div className="rounded-xl border border-border bg-destructive/10 px-4 py-2 text-center">
                                <p className="text-xs font-medium uppercase tracking-wider text-destructive">
                                    {t('pages.my_tasks.overdue_tasks')}
                                </p>
                                <p className="mt-1 text-2xl font-bold text-destructive">0</p>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Filter tabs */}
                <div className="flex gap-2 overflow-x-auto pb-1">
                    {filters.map((filter) => (
                        <button
                            key={filter.key}
                            type="button"
                            onClick={() => setActiveFilter(filter.key)}
                            className={cn(
                                'whitespace-nowrap rounded-lg px-4 py-2 text-sm font-medium transition-colors',
                                activeFilter === filter.key
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground',
                            )}
                        >
                            {filter.label}
                        </button>
                    ))}
                </div>

                {/* Task list */}
                <section className="rounded-2xl border border-border bg-card shadow-sm">
                    <div className="flex flex-col items-center justify-center gap-4 py-16 text-center">
                        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-muted">
                            <ClipboardList className="h-7 w-7 text-muted-foreground" />
                        </div>
                        <p className="text-sm text-muted-foreground">{t('pages.my_tasks.no_tasks')}</p>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

