import { useMemo } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import AppLayout from '@/layouts/AppLayout';
import { useTranslations } from '@/hooks/useTranslations';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import type { AppSectionRoute } from '@/app/routes';
import { PageHeader } from '@/components/layout/PageHeader';

interface CustomersPageProps {
    route: AppSectionRoute;
}

function formatCreatedAt(value: unknown): string {
    if (!value) return 'â€”';
    const date = new Date(String(value));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString();
}

export default function CustomersPage({ route }: CustomersPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/customers',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.customers.create_title'),
            editTitle: t('pages.customers.edit_title'),
            fields: [
                {
                    key: 'name',
                    label: t('pages.customers.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.customers.category_general'),
                },
                {
                    key: 'legal_reg',
                    label: t('pages.customers.column_legal_reg'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    category: t('pages.customers.category_general'),
                },
                {
                    key: 'dpo_name',
                    label: t('pages.customers.column_dpo_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    hiddenInTable: true,
                    category: t('pages.customers.category_contact'),
                },
                {
                    key: 'dpo_email',
                    label: t('pages.customers.column_dpo_email'),
                    type: 'email',
                    sortable: true,
                    editable: true,
                    hiddenInTable: true,
                    category: t('pages.customers.category_contact'),
                },
                {
                    key: 'description',
                    label: t('pages.customers.column_description'),
                    type: 'textarea',
                    editable: true,
                    masterDescription: true,
                    category: t('pages.customers.category_general'),
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
                    category: t('pages.customers.category_general'),
                },
                {
                    key: 'created_at',
                    label: t('pages.customers.column_created_at'),
                    type: 'date',
                    sortable: true,
                    editable: false,
                    renderCell: (value) => formatCreatedAt(value),
                    renderDetail: (value) => formatCreatedAt(value),
                    category: t('pages.customers.category_metadata'),
                },
            ],
        }),
        [t]
    );

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.customers.title')}
                    description={t('pages.customers.description')}
                    icon={<MaterialSymbol name="apartment" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                {/* CRUD Table */}
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
