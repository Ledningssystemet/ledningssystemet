import { useMemo } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface AgreementsPageProps {
    route: AppSectionRoute;
}

interface SharedProps extends PageProps {
    auth?: {
        user?: {
            id: number;
        } | null;
    };
}

export default function AgreementsPage({ route }: AgreementsPageProps) {
    const { t } = useTranslations();
    const page = usePage<SharedProps>();
    const currentUserId = page.props.auth?.user?.id ?? null;

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/agreements',
        perPage: 25,
        defaultSort: 'name',
        selectFields: [
            'id',
            'name',
            'description',
            'responsible_user_id',
            'customer_id',
            'supplier_id',
            'startdate',
            'reminderdate',
            'enddate',
            'archived_at',
            'tags',
        ],
        createTitle: t('pages.agreements.create_title'),
        editTitle: t('pages.agreements.edit_title'),
        customQueryParams: (filters) => ({
            tag_id: filters.tag_id || undefined,
            show_my_only: filters.show_my_only || undefined,
            hide_without_issues: filters.hide_without_issues || undefined,
            show_archived: filters.show_archived || undefined,
        }),
        getItemStatus: (item) => {
            if (!item.responsible_user_id || !item.startdate || !item.enddate) {
                return 'danger';
            }

            if (item.archived_at) {
                return 'info';
            }

            return null;
        },
        rowActions: [
            {
                key: 'archive',
                label: t('pages.agreements.archive_action'),
                variant: 'outline',
                isVisible: (item) => Boolean(currentUserId && !item.archived_at && item.responsible_user_id === currentUserId),
                onClick: async (item) => {
                    if (!window.confirm(t('pages.agreements.archive_confirm'))) {
                        return;
                    }

                    const response = await fetch(`/api/agreements/${item.id}/archive`, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(t('pages.agreements.archive_failed'));
                    }
                },
            },
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.agreements.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.agreements.category_general'),
            },
            {
                key: 'tags',
                label: t('pages.agreements.column_tags'),
                type: 'inline-tags',
                editable: true,
                sortable: false,
                tags: true,
                optionsUrl: '/api/crud/tags?%24select=id,name&sort=name',
                createOptionUrl: '/api/crud/tags',
                optionValueKey: 'name',
                optionLabelKey: 'name',
                createOptionPayloadKey: 'name',
                category: t('pages.agreements.category_general'),
            },
            {
                key: 'customer_id',
                label: t('pages.agreements.column_customer'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/customers?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.agreements.none_option'),
                category: t('pages.agreements.category_association'),
            },
            {
                key: 'supplier_id',
                label: t('pages.agreements.column_supplier'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/suppliers?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.agreements.none_option'),
                category: t('pages.agreements.category_association'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.agreements.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.agreements.none_option'),
                category: t('pages.agreements.category_general'),
            },
            {
                key: 'description',
                label: t('pages.agreements.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                category: t('pages.agreements.category_general'),
            },
            {
                key: 'startdate',
                label: t('pages.agreements.column_startdate'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.agreements.category_dates'),
            },
            {
                key: 'reminderdate',
                label: t('pages.agreements.column_reminderdate'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.agreements.category_dates'),
            },
            {
                key: 'enddate',
                label: t('pages.agreements.column_enddate'),
                type: 'date',
                sortable: true,
                editable: true,
                category: t('pages.agreements.category_dates'),
            },
            {
                key: 'archived_at',
                label: t('pages.agreements.column_archived_at'),
                type: 'date',
                sortable: true,
                editable: false,
                hiddenInTable: true,
                category: t('pages.agreements.category_status'),
            },
            {
                key: 'tag_id',
                label: t('pages.agreements.filter_tag'),
                type: 'select',
                hidden: true,
                editable: false,
                filterable: true,
                optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
            },
            {
                key: 'show_my_only',
                label: t('pages.agreements.filter_show_my_only'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.agreements.option_yes') },
                ],
            },
            {
                key: 'hide_without_issues',
                label: t('pages.agreements.filter_hide_without_issues'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.agreements.option_yes') },
                ],
            },
            {
                key: 'show_archived',
                label: t('pages.agreements.filter_show_archived'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [
                    { value: '1', label: t('pages.agreements.option_yes') },
                ],
            },
        ],
    }), [t, currentUserId]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.agreements.title')}
                    description={t('pages.agreements.description')}
                    icon={<MaterialSymbol name="signature" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
