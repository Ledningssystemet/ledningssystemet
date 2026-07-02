import { useMemo } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import { usePage } from '@inertiajs/react';
import type { PageProps } from '@inertiajs/core';
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ObjectivesPageProps {
    route: AppSectionRoute;
}

interface SharedProps extends PageProps {
    auth?: {
        user?: {
            id: number;
        } | null;
    };
}

export default function ObjectivesPage({ route }: ObjectivesPageProps) {
    const { t } = useTranslations();
    const page = usePage<SharedProps>();
    const currentUserId = page.props.auth?.user?.id ?? null;

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/objectives',
        perPage: 25,
        defaultSort: 'due',
        selectFields: [
            'id',
            'name',
            'description',
            'action_plan',
            'responsible_user_id',
            'department_id',
            'due',
            'archived_at',
            'tags',
        ],
        createTitle: t('pages.objectives.create_title'),
        editTitle: t('pages.objectives.edit_title'),
        customQueryParams: (filters) => ({
            tag_id: filters.tag_id || undefined,
            responsible_user_id: filters.responsible_user_id || undefined,
            show_my_only: filters.show_my_only || undefined,
            show_archived: filters.show_archived || undefined,
        }),
        rowActions: [
            {
                key: 'archive',
                label: t('pages.objectives.archive_action'),
                variant: 'outline',
                isVisible: (item) => Boolean(currentUserId && !item.archived_at && item.responsible_user_id === currentUserId),
                onClick: async (item) => {
                    if (!window.confirm(t('pages.objectives.archive_confirm'))) {
                        return;
                    }

                    const response = await fetch(`/api/objectives/${item.id}/archive`, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error(t('pages.objectives.archive_failed'));
                    }
                },
            },
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.objectives.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.objectives.category_general'),
            },
            {
                key: 'tags',
                label: t('pages.objectives.column_tags'),
                type: 'inline-tags',
                editable: true,
                sortable: false,
                tags: true,
                optionsUrl: '/api/crud/tags?%24select=id,name&sort=name',
                createOptionUrl: '/api/crud/tags',
                optionValueKey: 'name',
                optionLabelKey: 'name',
                createOptionPayloadKey: 'name',
                category: t('pages.objectives.category_general'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.objectives.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                required: true,
                filterable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.objectives.none_assigned'),
                category: t('pages.objectives.category_general'),
            },
            {
                key: 'due',
                label: t('pages.objectives.column_due'),
                type: 'date',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.objectives.category_general'),
            },
            {
                key: 'department_id',
                label: t('pages.objectives.column_department'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.objectives.none_option'),
                category: t('pages.objectives.category_general'),
            },
            {
                key: 'description',
                label: t('pages.objectives.column_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                placeholder: t('pages.objectives.description_placeholder'),
                category: t('pages.objectives.category_description'),
            },
            {
                key: 'action_plan',
                label: t('pages.objectives.column_action_plan'),
                type: 'textarea',
                editable: true,
                placeholder: t('pages.objectives.action_plan_placeholder'),
                category: t('pages.objectives.category_plan'),
            },
            {
                key: 'archived_at',
                label: t('pages.objectives.column_archived_at'),
                type: 'date',
                sortable: true,
                editable: false,
                hiddenInTable: true,
                category: t('pages.objectives.category_status'),
            },
            {
                key: 'tag_id',
                label: t('pages.objectives.filter_tag'),
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
                label: t('pages.objectives.filter_show_my_only'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.objectives.option_yes') }],
            },
            {
                key: 'show_archived',
                label: t('pages.objectives.filter_show_archived'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.objectives.option_yes') }],
            },
        ],
    }), [t, currentUserId]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.objectives.title')}
                    description={t('pages.objectives.description')}
                    icon={<MaterialSymbol name="target" className="h-6 w-6 text-primary" />}
                    route={route}
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
