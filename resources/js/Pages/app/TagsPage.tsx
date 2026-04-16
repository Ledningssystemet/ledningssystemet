import { useMemo } from 'react';
import { Tags } from 'lucide-react';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface TagsPageProps {
    route: AppSectionRoute;
}

export default function TagsPage({ route }: TagsPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(() => ({
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
    }), [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.tags.title')}
                    description={t('pages.tags.description')}
                    icon={<Tags className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
