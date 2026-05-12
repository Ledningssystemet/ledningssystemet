import { useMemo } from 'react';
import { MaterialSymbol } from "@/components/ui/material-symbol";
import AppLayout from '@/layouts/AppLayout';
import { CrudModule } from '@/components/crud';
import type { CrudModuleConfig } from '@/components/crud';
import { PageHeader } from '@/components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface RisksPageProps {
    route: AppSectionRoute;
}

export default function RisksPage({ route }: RisksPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/risks',
            perPage: 25,
            defaultSort: '-updated_at',
            serializeFilters: false,
            selectFields: [
                'id',
                'name',
                'context_type',
                'context_id',
                'department_id',
                'riskowner_id',
                'scenariodescription',
                'consequencedescription',
                'probability_id',
                'consequence_id',
                'assessmentcomment',
                'assessed_at',
                'updated_at',
                'tags',
                'risk_controls',
                'risk_level_id',
            ],
            createTitle: t('pages.risks.create_title'),
            editTitle: t('pages.risks.edit_title'),
            customQueryParams: (filters) => ({
                tag_id: filters.tag_id || undefined,
                department_id: filters.department_id || undefined,
                context_type: filters.context_type || undefined,
                probability_id: filters.probability_id || undefined,
                consequence_id: filters.consequence_id || undefined,
                risk_level_id: filters.risk_level_id || undefined,
                riskowner_id: filters.riskowner_id || undefined,
                showmyonly: filters.showmyonly || undefined,
                showdraft: filters.showdraft || undefined,
                showapproved: filters.showapproved || undefined,
            }),
            subTableActions: [
                {
                    key: 'actions',
                    label: t('pages.risks.actions_button'),
                    icon: <MaterialSymbol name="checklist" className="h-4 w-4" />,
                    dialogMaxWidth: 'max-w-3xl',
                    dialogTitle: (item) => t('pages.risks.actions.panel_title', { risk: String(item.name || '') }),
                    dialogDescription: t('pages.risks.actions.panel_description'),
                    getConfig: (item): CrudModuleConfig => ({
                        apiUrl: '/api/crud/control-action-mappings',
                        perPage: 25,
                        defaultSort: 'id',
                        fixedFilters: { risk_id: Number(item.id) },
                        createDefaults: { risk_id: Number(item.id) },
                        selectFields: ['id', 'control_action_id', 'risk_id'],
                        createTitle: t('pages.risks.actions.create_title'),
                        editTitle: t('pages.risks.actions.edit_title'),
                        fields: [
                            {
                                key: 'control_action_id',
                                label: t('pages.risks.actions.column_control_action'),
                                type: 'select',
                                sortable: false,
                                editable: true,
                                required: true,
                                masterLabel: true,
                                optionsUrl: '/api/crud/control_actions?paginate=0&%24select=id,name&sort=name',
                                optionValueKey: 'id',
                                optionLabelKey: 'name',
                                placeholder: t('pages.risks.actions.select_action'),
                                category: t('pages.risks.actions.category_general'),
                            },
                        ],
                    }),
                },
            ],
            fields: [
                { key: 'name', label: t('pages.risks.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true, category: t('pages.risks.category_general') },
                { key: 'id', label: t('pages.risks.column_id'), type: 'number', sortable: true, editable: false, renderCell: (value) => `RISK-${value}`, category: t('pages.risks.category_metadata') },
                { key: 'updated_at', label: t('pages.risks.column_updated_at'), type: 'datetime', sortable: true, editable: false, category: t('pages.risks.category_metadata') },
                { key: 'department_id', label: t('pages.risks.column_department'), type: 'select', sortable: true, editable: true, filterable: true, required: true, optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.risks.select_department'), category: t('pages.risks.category_general') },
                { key: 'riskowner_id', label: t('pages.risks.column_risk_owner'), type: 'select', sortable: true, editable: true, filterable: true, optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.risks.none_assigned'), category: t('pages.risks.category_general') },
                { key: 'context_type', label: t('pages.risks.column_context_type'), type: 'select', editable: true, hiddenInTable: true, options: [
                    { value: 'Department', label: t('pages.risks.context_department') },
                    { value: 'Process', label: t('pages.risks.context_process') },
                    { value: 'Asset', label: t('pages.risks.context_asset') },
                    { value: 'InformationType', label: t('pages.risks.context_information_type') },
                    { value: 'Supplier', label: t('pages.risks.context_supplier') },
                    { value: 'ProcessActivity', label: t('pages.risks.context_process_activity') },
                    { value: 'Site', label: t('pages.risks.context_site') },
                    { value: 'Customer', label: t('pages.risks.context_customer') },
                ], category: t('pages.risks.category_context') },
                { key: 'context_id', label: t('pages.risks.column_context_id'), type: 'number', editable: true, hiddenInTable: true, category: t('pages.risks.category_context') },
                { key: 'scenariodescription', label: t('pages.risks.column_scenario_description'), type: 'textarea', editable: true, required: true, masterDescription: true, category: t('pages.risks.category_assessment') },
                { key: 'consequencedescription', label: t('pages.risks.column_consequence_description'), type: 'textarea', editable: true, hiddenInTable: true, category: t('pages.risks.category_assessment') },
                { key: 'probability_id', label: t('pages.risks.column_probability'), type: 'select', editable: true, filterable: true, sortable: true, optionsUrl: '/api/crud/probability-levels?paginate=0&%24select=id,name&sort=-ordinal', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.risks.not_assessed'), category: t('pages.risks.category_assessment') },
                { key: 'consequence_id', label: t('pages.risks.column_consequence'), type: 'select', editable: true, filterable: true, sortable: true, optionsUrl: '/api/crud/consequence-levels?paginate=0&%24select=id,name&sort=-ordinal', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.risks.not_assessed'), category: t('pages.risks.category_assessment') },
                { key: 'assessmentcomment', label: t('pages.risks.column_assessment_comment'), type: 'textarea', editable: true, category: t('pages.risks.category_assessment') },
                { key: 'risk_controls', label: t('pages.risks.column_controls'), type: 'multiselect', editable: true, optionsUrl: '/api/crud/controls?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.risks.select_controls'), category: t('pages.risks.category_actions') },
                { key: 'tags', label: t('pages.risks.column_tags'), type: 'inline-tags', editable: true, sortable: false, tags: true, optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name', createOptionUrl: '/api/crud/tags', optionValueKey: 'name', optionLabelKey: 'name', createOptionPayloadKey: 'name', category: t('pages.risks.category_general') },
                { key: 'assessed_at', label: t('pages.risks.column_assessed_at'), type: 'datetime', sortable: true, editable: false, hiddenInTable: true, category: t('pages.risks.category_status') },
            ],
            filterFields: [
                { key: 'tag_id', label: t('pages.risks.filter_tag'), type: 'select', optionsUrl: '/api/crud/tags?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name' },
                { key: 'risk_level_id', label: t('pages.risks.filter_risk_level'), type: 'select', optionsUrl: '/api/crud/risk-levels?paginate=0&%24select=id,name&sort=-ordinal', optionValueKey: 'id', optionLabelKey: 'name' },
                { key: 'showmyonly', label: t('pages.risks.filter_show_my_only'), type: 'boolean', options: [{ value: '1', label: t('pages.risks.option_yes') }] },
                { key: 'showdraft', label: t('pages.risks.filter_show_draft'), type: 'boolean', options: [{ value: '1', label: t('pages.risks.option_yes') }] },
                { key: 'showapproved', label: t('pages.risks.filter_show_approved'), type: 'boolean', options: [{ value: '1', label: t('pages.risks.option_yes') }] },
            ],
        }),
        [t]
    );

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.risks.title')}
                    description={t('pages.risks.description')}
                    icon={<MaterialSymbol name="gpp_bad" className="h-6 w-6 text-primary" />}
                    route={route}
                />
                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>
        </AppLayout>
    );
}
