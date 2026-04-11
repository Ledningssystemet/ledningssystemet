import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Tags } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { APP_HOME_PATH } from '@/app/routes';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface TagsPageProps {
    route: AppSectionRoute;
}

export default function TagsPage({ route }: TagsPageProps) {
    const { t } = useTranslations();

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.tags.title') });

        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/tags',
        perPage: 25,
        defaultSort: 'name',
        selectFields: ['id', 'name', 'created_at'],
        createTitle: t('pages.tags.create_title'),
        editTitle: t('pages.tags.edit_title'),
        fields: [
            {
                key: 'name',
                label: t('pages.tags.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
            },
            {
                key: 'usageinformation',
                label: t('pages.tags.column_usage_information'),
                type: 'textarea',
                editable: false,
                sortable: false,
                renderCell: (value) => String(value || t('pages.tags.no_usage')),
                renderDetail: (value) => String(value || t('pages.tags.no_usage')),
            },
            {
                key: 'created_at',
                label: t('pages.tags.column_created_at'),
                type: 'date',
                sortable: true,
                editable: false,
                renderCell: (value) => {
                    if (!value) return '—';
                    const date = new Date(String(value));
                    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString();
                },
                renderDetail: (value) => {
                    if (!value) return '—';
                    const date = new Date(String(value));
                    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString();
                },
            },
        ],
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.tags.title')}</span>
                </nav>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Tags className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">{t('pages.tags.title')}</h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.tags.description')}
                            </p>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
