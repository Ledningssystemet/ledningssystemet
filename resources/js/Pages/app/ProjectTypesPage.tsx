import { useMemo } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ProjectTypesPageProps {
    route: AppSectionRoute;
}

export default function ProjectTypesPage({ route }: ProjectTypesPageProps) {
    const { t } = useTranslations();

    const config: CrudModuleConfig = useMemo(
        () => ({
            apiUrl: '/api/crud/risk-project-types',
            perPage: 25,
            defaultSort: 'name',
            createTitle: t('pages.project_types.create_title'),
            editTitle: t('pages.project_types.edit_title'),
            selectFields: ['id', 'name', 'description'],
            subTableActions: [
                {
                    key: 'templates',
                    label: t('pages.project_types.open_templates_button'),
                    icon: <MaterialSymbol name="checklist" className="h-4 w-4" />,
                    dialogMaxWidth: 'max-w-5xl',
                    dialogTitle: (item) => t('pages.project_types.templates.panel_title', { type: String(item.name || '') }),
                    dialogDescription: t('pages.project_types.templates.panel_description'),
                    getConfig: (item): CrudModuleConfig => {
                        return {
                            apiUrl: '/api/crud/risk-project-type-risk-templates',
                            perPage: 100,
                            defaultSort: 'name',
                            fixedFilters: { project_type_id: Number(item.id) },
                            createDefaults: { project_type_id: Number(item.id) },
                            canCreate: true,
                            canEdit: true,
                            canDelete: true,
                            createTitle: t('pages.project_types.templates.create_title'),
                            editTitle: t('pages.project_types.templates.edit_title'),
                            selectFields: ['id', 'project_type_id', 'name', 'scenariodescription', 'consequencedescription', 'probability_id', 'consequence_id', 'controls'],
                            fields: [
                                { key: 'project_type_id', label: t('pages.project_types.templates.column_project_type'), type: 'number', editable: false, hidden: true },
                                { key: 'name', label: t('pages.project_types.templates.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true, category: t('pages.project_types.templates.category_general') },
                                { key: 'scenariodescription', label: t('pages.project_types.templates.column_risk_scenario'), type: 'textarea', sortable: false, editable: true, category: t('pages.project_types.templates.category_assessment') },
                                { key: 'consequencedescription', label: t('pages.project_types.templates.column_consequence_description'), type: 'textarea', sortable: false, editable: true, category: t('pages.project_types.templates.category_assessment') },
                                { key: 'probability_id', label: t('pages.project_types.templates.column_probability'), type: 'select', sortable: true, editable: true, optionsUrl: '/api/crud/probability-levels?paginate=0&%24select=id,name&sort=-ordinal', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.project_types.templates.not_assessed'), category: t('pages.project_types.templates.category_assessment') },
                                { key: 'consequence_id', label: t('pages.project_types.templates.column_consequence'), type: 'select', sortable: true, editable: true, optionsUrl: '/api/crud/consequence-levels?paginate=0&%24select=id,name&sort=-ordinal', optionValueKey: 'id', optionLabelKey: 'name', placeholder: t('pages.project_types.templates.not_assessed'), category: t('pages.project_types.templates.category_assessment') },
                                { key: 'controls', label: t('pages.project_types.templates.column_controls'), type: 'multiselect', sortable: false, editable: true, optionsUrl: '/api/crud/controls?paginate=0&%24select=id,name&sort=name', optionValueKey: 'id', optionLabelKey: 'name', category: t('pages.project_types.templates.category_controls') },
                            ],
                        };
                    },
                },
            ],
            fields: [
                { key: 'name', label: t('pages.project_types.column_name'), type: 'text', sortable: true, editable: true, required: true, masterLabel: true, category: t('pages.project_types.category_general') },
                { key: 'description', label: t('pages.project_types.column_description'), type: 'textarea', sortable: false, editable: true, required: true, masterDescription: true, category: t('pages.project_types.category_general') },
            ],
        }),
        [t]
    );

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.project_types.title')}
                    description={t('pages.project_types.description')}
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
