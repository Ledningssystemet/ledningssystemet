import { useMemo } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ObservationsPageProps {
    route: AppSectionRoute;
}

export default function ObservationsPage({ route }: ObservationsPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/findings',
        perPage: 25,
        defaultSort: '-created_at',
        selectFields: [
            'id',
            'name',
            'description',
            'department_id',
            'nonconformity',
            'consequence',
            'rootcause',
            'immediateaction',
            'preventativeaction',
            'estimated_cost',
            'distribution_analysis',
            'finished_at',
            'created_at',
            'created_by',
        ],
        createTitle: t('pages.observations.create_title'),
        editTitle: t('pages.observations.edit_title'),
        customQueryParams: (filters) => ({
            nonconformity: filters.nonconformity || undefined,
            show_unhandled: filters.show_unhandled !== false ? '1' : undefined,
            show_handled: filters.show_handled || undefined,
        }),
        subTableActions: [
            {
                key: 'actions',
                label: t('pages.observations.actions_button'),
                icon: <MaterialSymbol name="checklist" className="h-4 w-4" />,
                dialogMaxWidth: 'max-w-3xl',
                dialogTitle: (item) => t('pages.observations.actions.panel_title', { observation: String(item.name || '') }),
                dialogDescription: t('pages.observations.actions.panel_description'),
                getConfig: (item): CrudModuleConfig => ({
                    apiUrl: '/api/crud/control-action-mappings',
                    perPage: 25,
                    defaultSort: 'id',
                    fixedFilters: { finding_id: Number(item.id) },
                    createDefaults: { finding_id: Number(item.id) },
                    selectFields: ['id', 'control_action_id', 'finding_id'],
                    createTitle: t('pages.observations.actions.create_title'),
                    editTitle: t('pages.observations.actions.edit_title'),
                    fields: [
                        {
                            key: 'control_action_id',
                            label: t('pages.observations.actions.column_control_action'),
                            type: 'select',
                            sortable: false,
                            editable: true,
                            required: true,
                            masterLabel: true,
                            optionsUrl: '/api/crud/control-actions?paginate=0&%24select=id,name&sort=name',
                            optionValueKey: 'id',
                            optionLabelKey: 'name',
                            placeholder: t('pages.observations.actions.select_action'),
                            category: t('pages.observations.actions.category_general'),
                        },
                    ],
                }),
            },
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.observations.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.observations.category_general'),
            },
            {
                key: 'description',
                label: t('pages.observations.column_description'),
                type: 'textarea',
                editable: true,
                required: true,
                masterDescription: true,
                category: t('pages.observations.category_general'),
            },
            {
                key: 'department_id',
                label: t('pages.observations.column_department'),
                type: 'select',
                sortable: true,
                editable: true,
                filterable: true,
                required: true,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.observations.none_assigned'),
                category: t('pages.observations.category_general'),
            },
            {
                key: 'nonconformity',
                label: t('pages.observations.column_nonconformity'),
                type: 'boolean',
                sortable: true,
                editable: true,
                filterable: true,
                required: true,
                options: [
                    { value: '1', label: t('pages.observations.filter_nonconformity_yes') },
                    { value: '0', label: t('pages.observations.filter_nonconformity_no') },
                ],
                category: t('pages.observations.category_general'),
            },
            {
                key: 'estimated_cost',
                label: t('pages.observations.column_estimated_cost'),
                type: 'number',
                editable: true,
                sortable: true,
                category: t('pages.observations.category_general'),
            },
            {
                key: 'consequence',
                label: t('pages.observations.column_consequence'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'rootcause',
                label: t('pages.observations.column_rootcause'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'immediateaction',
                label: t('pages.observations.column_immediate_action'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'preventativeaction',
                label: t('pages.observations.column_preventative_action'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'distribution_analysis',
                label: t('pages.observations.column_distribution_analysis'),
                type: 'textarea',
                editable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_nonconformity'),
            },
            {
                key: 'finished_at',
                label: t('pages.observations.column_finished_at'),
                type: 'datetime',
                editable: false,
                sortable: true,
                hiddenInTable: true,
                category: t('pages.observations.category_status'),
            },
            {
                key: 'created_at',
                label: t('pages.observations.column_created_at'),
                type: 'datetime',
                editable: false,
                sortable: true,
                category: t('pages.observations.category_status'),
            },
            {
                key: 'created_by',
                label: t('pages.observations.column_created_by'),
                type: 'select',
                editable: false,
                sortable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                hiddenInTable: true,
                category: t('pages.observations.category_status'),
            },
        ],
        filterFields: [
            {
                key: 'show_unhandled',
                label: t('pages.observations.filter_show_unhandled'),
                type: 'boolean',
                options: [{ value: '1', label: t('pages.observations.option_yes') }],
            },
            {
                key: 'show_handled',
                label: t('pages.observations.filter_show_handled'),
                type: 'boolean',
                options: [{ value: '1', label: t('pages.observations.option_yes') }],
            },
        ],
    }), [t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.observations.title')}
                    description={t('pages.observations.description')}
                    icon={<MaterialSymbol name="error" className="h-6 w-6 text-primary" />}
                    route={route}
                />
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
