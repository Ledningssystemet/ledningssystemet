import { useEffect } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { APP_HOME_PATH } from '@/app/routes';
import { Link } from 'react-router-dom';
import { Building2 } from 'lucide-react';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import type { AppSectionRoute } from '@/app/routes';

interface CustomersPageProps {
    route: AppSectionRoute;
}

export default function CustomersPage({ route }: CustomersPageProps) {
    const { t } = useTranslations();

    const formatCreatedAt = (value: unknown) => {
        if (!value) return '—';
        const date = new Date(String(value));
        return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString();
    };

    useEffect(() => {
        const previousTitle = document.title;
        document.title = t('ui.app.page_title_suffix', { page: t('pages.customers.title') });
        return () => {
            document.title = previousTitle;
        };
    }, [t]);

    const config: CrudModuleConfig = {
        apiUrl: '/api/crud/customers',
        perPage: 25,
        defaultSort: 'name',
        fields: [
            {
                key: 'name',
                label: t('pages.customers.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
            },
            {
                key: 'legal_reg',
                label: t('pages.customers.column_legal_reg'),
                type: 'text',
                sortable: true,
                editable: true,
            },
            {
                key: 'dpo_name',
                label: t('pages.customers.column_dpo_name'),
                type: 'text',
                sortable: true,
                editable: true,
                hiddenInTable: true,
            },
            {
                key: 'dpo_email',
                label: t('pages.customers.column_dpo_email'),
                type: 'email',
                sortable: true,
                editable: true,
                hiddenInTable: true,
            },
            {
                key: 'description',
                label: t('pages.customers.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
            },
            {
                key: 'tags',
                label: t('pages.customers.column_tags'),
                type: 'inline-tags',
                editable: true,
                sortable: false,
                tags: true,
                optionsUrl: '/api/crud/tags?%24select=id,name&sort=name',
                createOptionUrl: '/api/crud/tags',
                optionValueKey: 'name',
                optionLabelKey: 'name',
                createOptionPayloadKey: 'name',
            },
            {
                key: 'created_at',
                label: t('pages.customers.column_created_at'),
                type: 'date',
                sortable: true,
                editable: false,
                renderCell: (value) => formatCreatedAt(value),
                renderDetail: (value) => formatCreatedAt(value),
            },
        ],
    };

    return (
        <AppLayout>
            <div className="space-y-6">
                {/* Breadcrumb */}
                <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-xs text-muted-foreground">
                    <Link to={APP_HOME_PATH} className="transition-colors hover:text-foreground">
                        {t('ui.app.breadcrumb_home')}
                    </Link>
                    <span>/</span>
                    <span>{t('pages.customers.title')}</span>
                </nav>

                {/* Page header */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                            <Building2 className="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                                {t('pages.customers.title')}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {route.description ?? t('pages.customers.description')}
                            </p>
                        </div>
                    </div>
                </section>

                {/* CRUD Table */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
