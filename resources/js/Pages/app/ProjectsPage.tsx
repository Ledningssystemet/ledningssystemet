import { useMemo, useState } from 'react';
import { MaterialSymbol } from "@/Components/ui/material-symbol";
import AppLayout from '@/Layouts/AppLayout';
import { CrudModule } from '@/Components/crud';
import type { CrudModuleConfig } from '@/Components/crud';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { PageHeader } from '@/Components/layout/PageHeader';
import { useTranslations } from '@/hooks/useTranslations';
import type { AppSectionRoute } from '@/app/routes';

interface ProjectRisksPageProps {
    route: AppSectionRoute;
}

export default function ProjectsPage({ route }: ProjectRisksPageProps) {
    const { t } = useTranslations();
    const [activeProjectForRisks, setActiveProjectForRisks] = useState<Record<string, any> | null>(null);

    const config: CrudModuleConfig = useMemo(() => ({
        apiUrl: '/api/crud/projects',
        perPage: 25,
        defaultSort: '-updated_at',
        selectFields: [
            'id',
            'name',
            'project_type_id',
            'department_id',
            'responsible_user_id',
            'start_date',
            'end_date',
            'scopedescription',
            'purposedescription',
            'users',
            'archived_at',
            'updated_at',
        ],
        createTitle: t('pages.projects.create_title'),
        editTitle: t('pages.projects.edit_title'),
        customQueryParams: (filters) => ({
            show_my_only: filters.show_my_only || undefined,
            show_archived: filters.show_archived || undefined,
        }),
        rowActions: [
            {
                key: 'risks',
                label: t('pages.projects.open_risks_button'),
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => {
                    setActiveProjectForRisks(item);
                },
            },
            {
                key: 'archive_toggle',
                label: t('pages.projects.archive_toggle_button'),
                variant: 'outline',
                onClick: async (item) => {
                    const isArchived = Boolean(item.archived_at);
                    const confirmed = window.confirm(
                        isArchived
                            ? t('pages.projects.unarchive_confirm')
                            : t('pages.projects.archive_confirm')
                    );

                    if (!confirmed) {
                        return;
                    }

                    const response = await fetch(`/api/crud/projects/${item.id}`, {
                        method: 'PATCH',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            archived_at: isArchived ? null : new Date().toISOString(),
                        }),
                    });

                    if (!response.ok) {
                        throw new Error(t('pages.projects.archive_toggle_failed'));
                    }
                },
            },
            {
                key: 'export',
                label: t('pages.projects.export_excel_row'),
                variant: 'outline',
                refreshOnComplete: false,
                onClick: (item) => {
                    window.open(`/api/v1/ReportCentral/Project/${item.id}`, '_blank', 'noopener,noreferrer');
                },
            },
        ],
        fields: [
            {
                key: 'name',
                label: t('pages.projects.column_name'),
                type: 'text',
                sortable: true,
                editable: true,
                required: true,
                masterLabel: true,
                category: t('pages.projects.category_general'),
            },
            {
                key: 'updated_at',
                label: t('pages.projects.column_updated_at'),
                type: 'datetime',
                sortable: true,
                editable: false,
                category: t('pages.projects.category_status'),
            },
            {
                key: 'project_type_id',
                label: t('pages.projects.column_project_type'),
                type: 'select',
                sortable: true,
                editable: true,
                optionsUrl: '/api/crud/risk-project-types?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.projects.none_option'),
                category: t('pages.projects.category_general'),
            },
            {
                key: 'department_id',
                label: t('pages.projects.column_department'),
                type: 'select',
                sortable: true,
                editable: true,
                required: true,
                filterable: true,
                optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.projects.select_department'),
                category: t('pages.projects.category_general'),
            },
            {
                key: 'responsible_user_id',
                label: t('pages.projects.column_responsible_user'),
                type: 'select',
                sortable: true,
                editable: true,
                required: true,
                filterable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                placeholder: t('pages.projects.none_assigned'),
                category: t('pages.projects.category_general'),
            },
            {
                key: 'users',
                label: t('pages.projects.column_participants'),
                type: 'multiselect',
                editable: true,
                optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                optionValueKey: 'id',
                optionLabelKey: 'name',
                category: t('pages.projects.category_general'),
            },
            {
                key: 'start_date',
                label: t('pages.projects.column_start_date'),
                type: 'date',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.projects.category_schedule'),
            },
            {
                key: 'end_date',
                label: t('pages.projects.column_end_date'),
                type: 'date',
                sortable: true,
                editable: true,
                required: true,
                category: t('pages.projects.category_schedule'),
            },
            {
                key: 'scopedescription',
                label: t('pages.projects.column_scope_description'),
                type: 'textarea',
                editable: true,
                category: t('pages.projects.category_description'),
            },
            {
                key: 'purposedescription',
                label: t('pages.projects.column_purpose_description'),
                type: 'textarea',
                editable: true,
                masterDescription: true,
                category: t('pages.projects.category_description'),
            },
            {
                key: 'show_my_only',
                label: t('pages.projects.filter_show_my_only'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.projects.option_yes') }],
            },
            {
                key: 'show_archived',
                label: t('pages.projects.filter_show_archived'),
                type: 'boolean',
                hidden: true,
                editable: false,
                filterable: true,
                options: [{ value: '1', label: t('pages.projects.option_yes') }],
            },
            {
                key: 'archived_at',
                label: t('pages.projects.column_archived_at'),
                type: 'datetime',
                sortable: true,
                editable: false,
                hiddenInTable: true,
                category: t('pages.projects.category_status'),
            },
        ],
    }), [t]);

    const projectRisksConfig: CrudModuleConfig | null = useMemo(() => {
        if (!activeProjectForRisks?.id) {
            return null;
        }

        const isArchived = Boolean(activeProjectForRisks.archived_at);

        return {
            apiUrl: '/api/crud/risks',
            perPage: 25,
            defaultSort: '-updated_at',
            fixedFilters: { project_id: Number(activeProjectForRisks.id) },
            createDefaults: { project_id: Number(activeProjectForRisks.id) },
            canCreate: !isArchived,
            canEdit: !isArchived,
            canDelete: !isArchived,
            createTitle: t('pages.projects.risks.create_title'),
            editTitle: t('pages.projects.risks.edit_title'),
            selectFields: [
                'id',
                'name',
                'department_id',
                'riskowner_id',
                'scenariodescription',
                'consequencedescription',
                'probability_id',
                'consequence_id',
                'assessmentcomment',
                'assessed_at',
            ],
            fields: [
                {
                    key: 'name',
                    label: t('pages.projects.risks.column_name'),
                    type: 'text',
                    sortable: true,
                    editable: true,
                    required: true,
                    masterLabel: true,
                    category: t('pages.projects.risks.category_general'),
                },
                {
                    key: 'department_id',
                    label: t('pages.projects.risks.column_department'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    required: true,
                    optionsUrl: '/api/crud/departments?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.projects.risks.select_department'),
                    category: t('pages.projects.risks.category_general'),
                },
                {
                    key: 'riskowner_id',
                    label: t('pages.projects.risks.column_risk_owner'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    optionsUrl: '/api/crud/users?paginate=0&%24select=id,name&sort=name',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.projects.risks.none_assigned'),
                    category: t('pages.projects.risks.category_general'),
                },
                {
                    key: 'scenariodescription',
                    label: t('pages.projects.risks.column_scenario_description'),
                    type: 'textarea',
                    editable: true,
                    required: true,
                    masterDescription: true,
                    category: t('pages.projects.risks.category_assessment'),
                },
                {
                    key: 'consequencedescription',
                    label: t('pages.projects.risks.column_consequence_description'),
                    type: 'textarea',
                    editable: true,
                    category: t('pages.projects.risks.category_assessment'),
                },
                {
                    key: 'probability_id',
                    label: t('pages.projects.risks.column_probability'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    optionsUrl: '/api/crud/probability-levels?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.projects.risks.not_assessed'),
                    category: t('pages.projects.risks.category_assessment'),
                },
                {
                    key: 'consequence_id',
                    label: t('pages.projects.risks.column_consequence'),
                    type: 'select',
                    sortable: true,
                    editable: true,
                    optionsUrl: '/api/crud/consequence-levels?paginate=0&%24select=id,name&sort=-ordinal',
                    optionValueKey: 'id',
                    optionLabelKey: 'name',
                    placeholder: t('pages.projects.risks.not_assessed'),
                    category: t('pages.projects.risks.category_assessment'),
                },
                {
                    key: 'assessmentcomment',
                    label: t('pages.projects.risks.column_assessment_comment'),
                    type: 'textarea',
                    editable: true,
                    category: t('pages.projects.risks.category_assessment'),
                },
                {
                    key: 'assessed_at',
                    label: t('pages.projects.risks.column_assessed_at'),
                    type: 'datetime',
                    sortable: true,
                    editable: false,
                    category: t('pages.projects.risks.category_status'),
                },
            ],
        };
    }, [activeProjectForRisks, t]);

    return (
        <AppLayout>
            <div className="space-y-6">
                <PageHeader
                    title={t('pages.projects.title')}
                    description={t('pages.projects.description')}
                    icon={<MaterialSymbol name="gpp_bad" className="h-6 w-6 text-primary" />}
                    route={route}
                    actions={
                        <a
                            href="/api/v1/ReportCentral/Project/0"
                            className="flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                        >
                            <MaterialSymbol name="download" className="h-4 w-4" />
                            {t('pages.projects.export_excel')}
                        </a>
                    }
                />

                <section className="rounded-2xl border border-border bg-card p-6 shadow-sm">
                    <CrudModule config={config} />
                </section>
            </div>

            {projectRisksConfig && (
                <Dialog open={Boolean(activeProjectForRisks)} onOpenChange={(open) => !open && setActiveProjectForRisks(null)}>
                    <DialogContent className="max-w-8xl">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <MaterialSymbol name="checklist" className="h-5 w-5" />
                                {t('pages.projects.risks.panel_title', {
                                    project: String(activeProjectForRisks?.name || ''),
                                })}
                            </DialogTitle>
                            <DialogDescription>{t('pages.projects.risks.panel_description')}</DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4">
                            <div className="flex justify-end">
                                <Button type="button" variant="outline" size="sm" onClick={() => setActiveProjectForRisks(null)}>
                                    {t('pages.projects.risks.close_panel_button')}
                                </Button>
                            </div>
                            <CrudModule key={`project-risks-${activeProjectForRisks?.id}`} config={projectRisksConfig} />
                        </div>
                    </DialogContent>
                </Dialog>
            )}
        </AppLayout>
    );
}
